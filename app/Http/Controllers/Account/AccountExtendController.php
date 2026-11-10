<?php

namespace App\Http\Controllers\Account;

use App\Enums\CheckoutRequestApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\CheckoutRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * 延期申请：对已分配给当前登录用户的资产提交归还日延期请求。
 * 审批人可以是任意被选中的用户（默认仓库管理员），流程与普通
 * 资产申请完全一致：pending → approved/rejected。批准时资产的
 * expected_checkin 被更新为延期日期（由 AssetsController@approveRequest
 * 处理），不再走仓库出库环节；拒绝时仅通知申请人，资产归还日保持不变。
 */
class AccountExtendController extends Controller
{
    public function create(Asset $asset): View|RedirectResponse
    {
        if ((int) $asset->assigned_to !== (int) auth()->id() || $asset->assigned_type !== User::class) {
            return redirect()->route('account.assets')
                ->with('error', '该资产不在您的名下');
        }

        // 已有 pending 的延期申请则不再重复提交。
        $hasPending = CheckoutRequest::query()
            ->open()
            ->where('request_type', 'extend')
            ->where('extended_asset_id', $asset->id)
            ->exists();

        if ($hasPending) {
            return redirect()->route('account.assets')
                ->with('info', '您已提交过该资产的延期申请，请等待审批结果');
        }

        $warehouseAdminId = (int) config('checkout_request.warehouse_admin_id');
        $warehouseAdmin = $warehouseAdminId > 0
            ? User::find($warehouseAdminId)
            : null;

        return view('account.extend-request', [
            'asset' => $asset,
            'approvers' => User::query()->active()->orderBy('first_name')->get(),
            'defaultApproverId' => $warehouseAdmin?->id,
        ]);
    }

    /**
     * @return RedirectResponse
     */
    public function store(Request $request, Asset $asset)
    {
        if ((int) $asset->assigned_to !== (int) auth()->id() || $asset->assigned_type !== User::class) {
            return redirect()->route('account.assets')
                ->with('error', '该资产不在您的名下');
        }

        $minDate = $asset->expected_checkin
            ? \Carbon\Carbon::parse($asset->expected_checkin)->addDay()->format('Y-m-d')
            : now()->addDay()->format('Y-m-d');

        $validator = Validator::make($request->all(), [
            'expected_return_date' => ['required', 'date', "after_or_equal:{$minDate}"],
            'approver_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'expected_return_date.after_or_equal' => '延期日期必须晚于原归还日',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $approverId = (int) $request->input('approver_id');

        $checkoutRequest = CheckoutRequest::create([
            'user_id' => (int) auth()->id(),
            'quantity' => 1,
            'fulfilled_quantity' => 0,
            'request_type' => 'extend',
            'extended_asset_id' => $asset->id,
            'original_checkout_request_id' => null,
            'expected_return_date' => $request->input('expected_return_date'),
            'notes' => $request->input('notes'),
            'approver_id' => $approverId,
            'approval_status' => CheckoutRequestApprovalStatus::Pending,
            'state' => \App\Enums\CheckoutRequestState::Pending,
        ]);

        UserNotification::notifyExtendSubmitted($checkoutRequest);

        return redirect()->route('account.requested')
            ->with('success', '延期申请已提交，等待审批');
    }
}
