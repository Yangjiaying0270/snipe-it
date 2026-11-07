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
     */
    public function awaitingWarehouse(): self
    {
        return $this->open()
            ->where('approval_status', CheckoutRequestApprovalStatus::Approved->value);
    }
}
