<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentWalletState;
use App\Enums\QaReviewDecision;
use App\Models\QaReview;
use App\Services\Payments\PaymentStatusDeriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class PaymentStatusDeriverTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    private function makeReview($submission, QaReviewDecision $decision): QaReview
    {
        $review = QaReview::create([
            'submission_id' => $submission->id,
            'decision' => $decision,
            'reviewed_at' => now(),
        ]);

        return $review->setRelation('submission', $submission);
    }

    public function test_approval_pays_out_the_quests_configured_reward_immediately(): void
    {
        $quest = $this->makeQuest('QST-PAY-001');
        $quest->update(['reward_amount' => 350.00, 'reward_currency' => 'ETB']);
        $outlet = $this->makeOutlet('OUT-PAY-001');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-PAID', 'q1', 'yes', now());

        $review = $this->makeReview($submission, QaReviewDecision::Approve);
        $status = (new PaymentStatusDeriver)->deriveFrom($review);

        $this->assertSame(PaymentWalletState::Paid, $status->wallet_state);
        $this->assertEquals(350.00, $status->amount);
        $this->assertSame('ETB', $status->currency);
    }

    public function test_rejection_withholds_payout_but_still_records_the_amount_at_stake(): void
    {
        $quest = $this->makeQuest('QST-PAY-002');
        $quest->update(['reward_amount' => 350.00, 'reward_currency' => 'ETB']);
        $outlet = $this->makeOutlet('OUT-PAY-002');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-REJECTED', 'q1', 'yes', now());

        $review = $this->makeReview($submission, QaReviewDecision::Reject);
        $status = (new PaymentStatusDeriver)->deriveFrom($review);

        $this->assertSame(PaymentWalletState::Rejected, $status->wallet_state);
        // wallet_state (Rejected) is what says this was withheld — amount
        // still reflects what the reward would have been, so a "total
        // rejected" dashboard figure means something.
        $this->assertEquals(350.00, $status->amount);
        $this->assertSame('ETB', $status->currency);
    }

    public function test_backcheck_and_send_back_leave_payment_pending_with_amount_at_stake(): void
    {
        $quest = $this->makeQuest('QST-PAY-003');
        $quest->update(['reward_amount' => 350.00, 'reward_currency' => 'ETB']);
        $outlet = $this->makeOutlet('OUT-PAY-003');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-PENDING', 'q1', 'yes', now());

        $review = $this->makeReview($submission, QaReviewDecision::FlagBackcheck);
        $status = (new PaymentStatusDeriver)->deriveFrom($review);

        $this->assertSame(PaymentWalletState::Pending, $status->wallet_state);
        $this->assertEquals(350.00, $status->amount);
    }

    public function test_amount_is_null_when_the_quest_has_no_reward_configured(): void
    {
        $quest = $this->makeQuest('QST-PAY-004'); // no reward_amount set
        $outlet = $this->makeOutlet('OUT-PAY-004');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-NOREWARD', 'q1', 'yes', now());

        $review = $this->makeReview($submission, QaReviewDecision::Approve);
        $status = (new PaymentStatusDeriver)->deriveFrom($review);

        $this->assertNull($status->amount);
        $this->assertNull($status->currency);
    }
}
