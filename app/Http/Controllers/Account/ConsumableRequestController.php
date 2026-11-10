<?php

namespace App\Http\Controllers\Account;

use App\Enums\CheckoutRequestApprovalStatus;
use App\Enums\CheckoutRequestState;
use App\Http\Controllers\Controller;
use App\Models\CheckoutRequest;
use App\Models\Consumable;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 耗材领用申请 —— 直接跳过审批（auto_approved），进入仓库管理员的
 * 出库队列。仓库管理员可执行出库（fulfill）或撤销（cancel）。
 *
 * 抄送人（cc_user_ids）在仓库实际出库后收到一条通知：
 *   "<申请人> 领用了 <数量> 个「<耗材名>」，用途：<purpose>"。
 */
class ConsumableRequestController extends Controller
{
    /**
     * @return RedirectResponse
     */
    public function store(Request $request, Consumable $consumable)
    {
        $this->authorize('request', Consumable::class);

        $available = (int) ($consumable->numRemaining() ?? 0);

        $validator = Validator::make($request->all(), [
            'quantity' => ['required', 'integer', 'min:1', "max:{$available}"],
            'purpose' => ['required', 'string', 'max:500'],
            'cc_user_ids' => ['nullable', 'array'],
            'cc_user_ids.*' => ['integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $ccIds = array_values(array_filter((array) $request->input('cc_user_ids', [])));

        $checkoutRequest = CheckoutRequest::create([
            'user_id' => (int) auth()->id(),
            'quantity' => (int) $request->input('quantity'),
            'fulfilled_quantity' => 0,
            'notes' => $request->input('notes'),
            'request_type' => 'consumable',
            'purpose' => $request->input('purpose'),
            'cc_user_ids' => $ccIds !== [] ? $ccIds : null,
            'approval_status' => CheckoutRequestApprovalStatus::AutoApproved,
            'state' => CheckoutRequestState::Pending,
            // 耗材没有 approver，跳过审批环节；仓库管理员通过
            // warehouse_admin_id 配置自动成为出库队列的处理人。
        ]);

        // 绑定到 polymorphic item（Consumable）。
        $checkoutRequest->itemRequested()->associate($consumable);
        $checkoutRequest->save();

        // 通知仓库管理员有耗材待出库（AutoApproved 状态也会被 notifyApproved 处理）。
        UserNotification::notifyApproved($checkoutRequest);

        return redirect()->route('account.requested')
            ->with('success', '耗材申请已提交，等待仓库发放');
    }
}
