<?php

namespace App\Enums;

enum PaymentWalletState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Bonus = 'bonus';

    /**
     * The QA dashboard's PaymentStatus type only knows 'paid'|'pending'|
     * 'rejected'|'under_review'. PaymentStatusDeriver goes straight from an
     * Approve decision to Paid (see that class), so the Approved case here
     * exists only as a defensive intermediate state — it is not currently
     * written by that deriver.
     */
    public function toApiValue(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Approved => 'under_review',
            self::Rejected => 'rejected',
            self::Paid, self::Bonus => 'paid',
        };
    }
}
