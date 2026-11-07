<?php

namespace Tests\Feature\CheckoutRequests;

use App\Actions\CheckoutRequests\CreateCheckoutRequestAction;
use App\Enums\CheckoutRequestApprovalStatus;
use App\Enums\CheckoutRequestState;
use App\Models\Asset;
use App\Models\CheckoutRequest;
use App\Models\Location;
use App\Models\User;
use App\Models\UserNotification;
use Tests\TestCase;

/**
 * 覆盖「申请 → 审批 → 出库」的规则与通知：
 * 只有被指定的审批人能处理、批准/退回都不改写 approver_id、
 * 出库时用申请里的使用地址覆盖资产位置、同资产的其他待审批申请失效。
 */
class ApprovalWorkflowTest extends TestCase
{
    public function test_submitting_request_notifies_the_assigned_approver(): void
    {
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $location = Location::factory()->create();
        $asset = Asset::factory()->requestable()->create();

        $this->actingAs($requester);
        CreateCheckoutRequestAction::run($asset, $requester, 1, null, null, null, $location->id, $approver->id, '测试理由');

        $request = CheckoutRequest::where('user_id', $requester->id)->firstOrFail();

        $this->assertSame(CheckoutRequestApprovalStatus::Pending, $request->approval_status);
        $this->assertSame($approver->id, (int) $request->approver_id);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $approver->id,
            'type' => UserNotification::TYPE_REQUEST_SUBMITTED,
            'checkout_request_id' => $request->id,
        ]);
    }

    public function test_assigned_approver_can_approve_and_warehouse_is_notified(): void
    {
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $request = $this->makePendingRequest($requester, $approver);

        $this->actingAs($approver)
            ->post(route('requests.approve', $request->id))
            ->assertRedirect(route('requests.index'));

        $request->refresh();
        $this->assertSame(CheckoutRequestApprovalStatus::Approved, $request->approval_status);
        // 批准不改写审批人，申请人的选择保持不变。
        $this->assertSame($approver->id, (int) $request->approver_id);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => (int) config('checkout_request.warehouse_admin_id'),
            'type' => UserNotification::TYPE_REQUEST_APPROVED,
            'checkout_request_id' => $request->id,
        ]);
    }

    public function test_unassigned_user_cannot_approve(): void
    {
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $stranger = User::factory()->create();
        $request = $this->makePendingRequest($requester, $approver);

        $this->actingAs($stranger)
            ->post(route('requests.approve', $request->id))
            ->assertForbidden();

        $this->assertSame(
            CheckoutRequestApprovalStatus::Pending,
            $request->refresh()->approval_status
        );
    }

    public function test_reject_notifies_the_requester_and_keeps_the_approver(): void
    {
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $request = $this->makePendingRequest($requester, $approver);

        $this->actingAs($approver)
            ->post(route('requests.reject', $request->id))
            ->assertRedirect(route('requests.index'));

        $request->refresh();
        $this->assertSame(CheckoutRequestApprovalStatus::Rejected, $request->approval_status);
        $this->assertSame($approver->id, (int) $request->approver_id);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $requester->id,
            'type' => UserNotification::TYPE_REQUEST_REJECTED,
            'checkout_request_id' => $request->id,
        ]);
    }

    public function test_approved_request_cannot_be_cancelled_by_requester(): void
    {
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $asset = Asset::factory()->requestable()->create();
        $request = CheckoutRequest::factory()->create([
            'user_id' => $requester->id,
            'requestable_id' => $asset->id,
            'requestable_type' => Asset::class,
            'approver_id' => $approver->id,
            'state' => CheckoutRequestState::Pending,
            'approval_status' => CheckoutRequestApprovalStatus::Approved,
        ]);

        $this->actingAs($requester)
            ->post(route('account/request-item', ['itemType' => 'asset', 'itemId' => $asset->id]))
            ->assertSessionHas('error');

        $request->refresh();
        $this->assertSame(CheckoutRequestState::Pending, $request->state);
        $this->assertSame(CheckoutRequestApprovalStatus::Approved, $request->approval_status);
    }

    public function test_checkout_fulfills_winner_and_invalidates_competitors(): void
    {
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        $admin = User::factory()->create();
        $location = Location::factory()->create();
        $asset = Asset::factory()->requestable()->create();

        $winnerRequest = CheckoutRequest::factory()->create([
            'user_id' => $winner->id,
            'requestable_id' => $asset->id,
            'requestable_type' => Asset::class,
            'state' => CheckoutRequestState::Pending,
            'approval_status' => CheckoutRequestApprovalStatus::Approved,
        ]);
        $loserRequest = CheckoutRequest::factory()->create([
            'user_id' => $loser->id,
            'requestable_id' => $asset->id,
            'requestable_type' => Asset::class,
            'state' => CheckoutRequestState::Pending,
            'approval_status' => CheckoutRequestApprovalStatus::Pending,
        ]);

        // 出库时传入申请里的使用地址，资产位置应被覆盖为该地址。
        $asset->checkOut($winner, $admin, null, null, null, null, $location->id);

        // 不用 $asset->refresh()：assignedTo() 的 morphTo 关系名是 'assigned'，
        // refresh() 会尝试 load('assigned') 而抛 RelationNotFoundException，改查库取值。
        $checkedOutAsset = Asset::find($asset->id);
        $this->assertSame($winner->id, (int) $checkedOutAsset->assigned_to);
        $this->assertSame($location->id, (int) $checkedOutAsset->location_id);

        $winnerRequest->refresh();
        $this->assertSame(CheckoutRequestState::Fulfilled, $winnerRequest->state);
        $this->assertSame(CheckoutRequestApprovalStatus::Fulfilled, $winnerRequest->approval_status);

        $loserRequest->refresh();
        $this->assertSame(CheckoutRequestState::Canceled, $loserRequest->state);
        $this->assertSame(CheckoutRequestApprovalStatus::Invalidated, $loserRequest->approval_status);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $winner->id,
            'type' => UserNotification::TYPE_REQUEST_FULFILLED,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $loser->id,
            'type' => UserNotification::TYPE_REQUEST_INVALIDATED,
        ]);
    }

    public function test_checked_out_asset_is_no_longer_requestable(): void
    {
        $requester = User::factory()->create();
        $admin = User::factory()->create();
        $asset = Asset::factory()->requestable()->create();

        $this->assertTrue($asset->isFlaggedRequestable());

        $asset->checkOut($requester, $admin);

        $this->assertFalse(Asset::find($asset->id)->isFlaggedRequestable());
    }

    private function makePendingRequest(User $requester, User $approver): CheckoutRequest
    {
        return CheckoutRequest::factory()->create([
            'user_id' => $requester->id,
            'requestable_id' => Asset::factory()->requestable()->create()->id,
            'requestable_type' => Asset::class,
            'approver_id' => $approver->id,
            'state' => CheckoutRequestState::Pending,
            'approval_status' => CheckoutRequestApprovalStatus::Pending,
        ]);
    }
}
