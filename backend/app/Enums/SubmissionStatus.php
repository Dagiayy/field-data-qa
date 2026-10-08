<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Received = 'received';
    case PendingReview = 'pending_review';
    case AutoRejected = 'auto_rejected';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Backcheck = 'backcheck';
    case SentBack = 'sent_back';

    /**
     * The QA dashboard (frontend/src/types/index.ts) speaks a smaller,
     * simplified status vocabulary than the backend's — this is the one
     * translation point between the two.
     */
    public function toApiValue(): string
    {
        return match ($this) {
            self::Received, self::PendingReview => 'pending',
            self::AutoRejected, self::Rejected => 'rejected',
            self::Approved => 'approved',
            self::Backcheck => 'backcheck',
            self::SentBack => 'sent_back',
        };
    }

    public static function fromApiValue(string $value): self
    {
        return match ($value) {
            'pending' => self::PendingReview,
            'approved' => self::Approved,
            'rejected' => self::Rejected,
            'backcheck' => self::Backcheck,
            'sent_back' => self::SentBack,
            default => self::PendingReview,
        };
    }
}
