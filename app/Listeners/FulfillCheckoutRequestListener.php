<?php

namespace App\Listeners;

use App\Actions\CheckoutRequests\FulfillCheckoutRequestAction;
use App\Enums\ActionType;
use App\Enums\CheckoutRequestApprovalStatus;
use App\Enums\CheckoutRequestState;
use App\Events\CheckoutableCheckedOut;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\CheckoutRequest;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;

/**
 * Close the fulfillment loop when a checkout happens. Runs off the
 * CheckoutableCheckedOut event, so every checkout path (web
 * controllers, API, imports, tinker) closes matching requests
 * automatically instead of each controller wiring the hook itself.
 *
 * Registered AFTER LogListener in EventServiceProvider so by the
 * time this fires, the checkout Actionlog row exists and can be
 * linked from the CheckoutRequest via checkout_actionlog_id.
 *
 * Only fires for user-target checkouts. Location and child-asset
 * targets don't correspond to a requester (requests are user-
 * owned), so those checkouts have no matching request to close.
 *
 * LicenseSeat checkouts map to License for the request lookup:
 * requesters ask for a License (not a specific seat), and
 * Loggable::determineLogItemType stores License::class in the
 * checkout actionlog for the same reason.
 */
class FulfillCheckoutRequestListener
{
    public function subscribe($events): void
    {
        $events->listen(
            CheckoutableCheckedOut::class,
            self::class.'@onCheckedOut',
        );
    }

    public function onCheckedOut(CheckoutableCheckedOut $event): void
    {
        if (! $event->checkedOutTo instanceof User) {
            return;
        }

        // Map LicenseSeat -> License so the request lookup matches
        // the requestable_type the requester actually filed against.
        $checkoutable = $event->checkoutable;
        $requestableType = $checkoutable instanceof LicenseSeat
            ? License::class
            : $checkoutable::class;
        $requestableId = $checkoutable instanceof LicenseSeat
            ? $checkoutable->license_id
            : $checkoutable->id;

        $openRequests = CheckoutRequest::where('requestable_type', $requestableType)
            ->where('requestable_id', $requestableId)
            ->where('user_id', $event->checkedOutTo->id)
            ->where('state', CheckoutRequestState::Pending->value)
            ->get();

        // 资产是 1:1 的实体：一旦出库给某个人，同一资产上其他用户的待审批
        // 申请就再也没有兑现的可能，立即置为「已失效」（state=canceled +
        // approval_status=invalidated）。放在 openRequests 早退之前，
        // 这样即便管理员直接出库（领用人本来就没有申请）也照样清理排队者。
        // 仅限 Asset：配件/耗材/组件/许可证按数量分配，多人可各得一部分。
        if ($requestableType === Asset::class) {
            $this->invalidateCompetingRequests($requestableId, $event->checkedOutTo->id);
        }

        if ($openRequests->isEmpty()) {
            return;
        }

        // Find the checkout Actionlog LogListener just wrote (it
        // subscribes to the same event and runs first per the order
        // in EventServiceProvider). Match on all four polymorphic
        // slots plus action_type. Grab the most recent id in case
        // this user has multiple checkout rows for the same item
        // over its lifetime.
        $actionlog = Actionlog::where('item_type', $requestableType)
            ->where('item_id', $requestableId)
            ->where('target_type', User::class)
            ->where('target_id', $event->checkedOutTo->id)
            ->where('action_type', ActionType::Checkout->value)
            ->latest('id')
            ->first();

        // Pass the event's quantity through so partial-fulfillment
        // tracking works. A request for 5 pens that gets a checkout
        // of 3 should advance fulfilled_quantity by 3 (not by the
        // full remainder). Non-positive falls back to "consume the
        // whole remainder" in the action.
        $eventQty = $event->quantity > 0 ? $event->quantity : null;

        foreach ($openRequests as $request) {
            try {
                FulfillCheckoutRequestAction::run($request, $actionlog, $eventQty);
            } catch (\Throwable $e) {
                // Never let a fulfillment failure roll back the
                // checkout that already succeeded. Log and continue -
                // the row will surface on the admin queue for manual
                // cleanup rather than silently corrupting state.
                Log::warning('Failed to auto-fulfill CheckoutRequest #'.$request->id.': '.$e->getMessage());
            }
        }
    }

    /**
     * 把同一资产上其他用户的待处理申请标记为已失效。
     * 保留 winnerUserId 自己的申请（那条由 FulfillCheckoutRequestAction
     * 正常结转），其余 pending 行统一 state=canceled + approval_status=invalidated，
     * 走 cancelAndSync() 以同步清理日历预约事件。
     */
    private function invalidateCompetingRequests(int $assetId, int $winnerUserId): void
    {
        $competing = CheckoutRequest::where('requestable_type', Asset::class)
            ->where('requestable_id', $assetId)
            ->where('user_id', '!=', $winnerUserId)
            ->where('state', CheckoutRequestState::Pending->value)
            ->get();

        foreach ($competing as $request) {
            try {
                $request->approval_status = CheckoutRequestApprovalStatus::Invalidated;
                $request->cancelAndSync();
                UserNotification::notifyInvalidated($request);
            } catch (\Throwable $e) {
                Log::warning('Failed to invalidate CheckoutRequest #'.$request->id.': '.$e->getMessage());
            }
        }
    }
}
