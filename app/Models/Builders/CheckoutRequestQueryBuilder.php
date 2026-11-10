<?php

namespace App\Models\Builders;

use App\Enums\CheckoutRequestApprovalStatus;
use App\Enums\CheckoutRequestState;
use App\Models\CheckoutRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of CheckoutRequest
 *
 * @extends Builder<TModel>
 */
class CheckoutRequestQueryBuilder extends Builder
{
    public function open(): self
    {
        return $this->where('state', CheckoutRequestState::Pending->value);
    }

    public function pending(): self
    {
        return $this->where('state', CheckoutRequestState::Pending->value);
    }

    public function fulfilled(): self
    {
        return $this->where('state', CheckoutRequestState::Fulfilled->value);
    }

    public function canceled(): self
    {
        return $this->where('state', CheckoutRequestState::Canceled->value);
    }

    /**
     * Open rows still waiting for the approver picked by the requester.
     */
    public function awaitingApproval(): self
    {
        return $this->open()
            ->where('approval_status', CheckoutRequestApprovalStatus::Pending->value);
    }

    /**
     * Open rows approved and waiting for the warehouse to check out.
     * auto_approved 状态的耗材申请同样属于"等仓库出库"。
     */
    public function awaitingWarehouse(): self
    {
        return $this->open()
            ->whereIn('approval_status', [
                CheckoutRequestApprovalStatus::Approved->value,
                CheckoutRequestApprovalStatus::AutoApproved->value,
            ]);
    }

    /**
     * Open rows (any approval_status) for which the given user is
     * either the picked approver or the warehouse admin. Drives the
     * "待处理申请" sidebar badge count as well as the admin queue
     * default filter.
     */
    public function pendingApprovalForUser(int $userId): self
    {
        $warehouseAdminId = (int) config('checkout_request.warehouse_admin_id');

        return $this->open()
            ->where(function (Builder $q) use ($userId, $warehouseAdminId): void {
                $q->where('approver_id', $userId)
                    ->orWhere('approver_id', $warehouseAdminId)
                    ->orWhereNull('approver_id');
            });
    }

    /**
     * Count-only variant used by badge rendering.
     */
    public function pendingApprovalCountForUser(int $userId): int
    {
        return $this->pendingApprovalForUser($userId)->count();
    }

    /**
     * Open rows whose request_type is 'extend'. Asset return flow.
     */
    public function extensions(): self
    {
        return $this->open()->where('request_type', 'extend');
    }

    /**
     * Open rows whose request_type is 'consumable'. Consumable requisition flow.
     */
    public function consumableRequests(): self
    {
        return $this->open()->where('request_type', 'consumable');
    }
}
