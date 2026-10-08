<?php

namespace App\Services\Payments;

use App\Enums\PaymentWalletState;
use App\Enums\QaReviewDecision;
use App\Models\PaymentStatus;
use App\Models\QaReview;

/**
 * The single place payment_statuses rows are ever written from a QA
 * decision — payment status must only ever be derived from QA status, never
 * set independently (see project CLAUDE.md non-negotiable rules).
 *
 * Approve is where "payment integration" actually happens: the quest's
 * configured reward_amount is paid out immediately, no separate manual
 * disbursement step. There is no external payment gateway in this system's
 * tech stack (project CLAUDE.md — local-first, no paid services) — this is
 * the full extent of what "integrating the payment" means here: recording
 * the payout as settled against the agent's wallet_state, ready for
 * whatever local payout batch process reads `payment_statuses` next.
 *
 * `amount` always reflects what's AT STAKE for this submission (the
 * quest's configured reward) regardless of decision — it is `wallet_state`
 * that says whether that amount was actually paid, withheld, or is still
 * pending. Without that split, the dashboard could only ever show a total
 * for money already paid, never "how much was withheld on rejection" or
 * "how much is currently on hold" — both real, non-derived figures a QA
 * Lead needs.
 */
class PaymentStatusDeriver
{
    public function deriveFrom(QaReview $review): PaymentStatus
    {
        $submission = $review->submission;
        $quest = $submission->quest ?? $submission->quest()->first();

        $walletState = match ($review->decision) {
            QaReviewDecision::Approve => PaymentWalletState::Paid,
            QaReviewDecision::Reject => PaymentWalletState::Rejected,
            QaReviewDecision::FlagBackcheck, QaReviewDecision::SendBack => PaymentWalletState::Pending,
        };

        return PaymentStatus::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'agent_id' => $submission->agent_id,
                'wallet_state' => $walletState,
                'amount' => $quest?->reward_amount,
                'currency' => $quest?->reward_amount !== null ? $quest?->reward_currency : null,
            ],
        );
    }
}
