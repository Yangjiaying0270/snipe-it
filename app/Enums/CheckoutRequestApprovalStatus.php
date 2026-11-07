<?php

namespace App\Enums;

/**
 * Approval track for a CheckoutRequest.
 *
 * Deliberately separate from {@see CheckoutRequestState}: `state`
 * answers "is the request still open", while this answers "which
 * stage of the approval flow the request sits in".
 *
 *   pending      Waiting for the approver picked by the requester.
 *   approved     Approved, waiting for the warehouse admin to check
 *                the item out.
 *   rejected     Returned to the requester, who may edit or cancel.
 *   fulfilled    The checkout was completed.
 *   invalidated  Closed automatically because someone else received
 *                the same asset first.
 */
enum CheckoutRequestApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Fulfilled = 'fulfilled';
    case Invalidated = 'invalidated';

    public function label(): string
    {
        return match ($this) {
            self::Pending => trans('general.approval_status_pending'),
            self::Approved => trans('general.approval_status_approved'),
            self::Rejected => trans('general.approval_status_rejected'),
            self::Fulfilled => trans('general.approval_status_fulfilled'),
            self::Invalidated => trans('general.approval_status_invalidated'),
        };
    }

    /**
     * Bootstrap 3 label color class for status badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::Fulfilled => 'success',
            self::Invalidated => 'default',
        };
    }
}
