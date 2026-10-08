<?php

namespace Tests\Feature\Qa;

use App\Enums\QaReviewDecision;
use App\Models\QaReview;
use App\Models\RejectionReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class AgentRejectionFeedbackControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    public function test_returns_every_rejection_reason_for_the_agent_across_submissions(): void
    {
        $quest = $this->makeQuest('QST-FEEDBACK-001');
        $outlet = $this->makeOutlet('OUT-FEEDBACK-001');
        $reason = RejectionReason::create(['code' => 'BLURRY_PHOTO', 'label' => 'Photo Unclear or Obstructed', 'active' => true]);

        $sub1 = $this->makeSubmission($quest, $outlet, 'AGT-FEEDBACK', 'q1', 'yes', now()->subDay());
        $sub2 = $this->makeSubmission($quest, $outlet, 'AGT-FEEDBACK', 'q1', 'yes', now());

        QaReview::create([
            'submission_id' => $sub1->id,
            'decision' => QaReviewDecision::Reject,
            'reason_code' => $reason->id,
            'note' => 'Shelf photo too blurry to read prices.',
            'reviewed_at' => now()->subDay(),
        ]);
        QaReview::create([
            'submission_id' => $sub2->id,
            'decision' => QaReviewDecision::Reject,
            'reason_code' => $reason->id,
            'note' => 'Same issue again.',
            'reviewed_at' => now(),
        ]);

        // A different agent's rejection must never leak into this response.
        $otherSub = $this->makeSubmission($quest, $outlet, 'AGT-OTHER', 'q1', 'yes', now());
        QaReview::create([
            'submission_id' => $otherSub->id,
            'decision' => QaReviewDecision::Reject,
            'reason_code' => $reason->id,
            'reviewed_at' => now(),
        ]);

        $response = $this->getJson('/api/qa/agents/AGT-FEEDBACK/rejection-feedback');

        $response->assertOk();
        $response->assertJson([
            'agent_id' => 'AGT-FEEDBACK',
            'total_rejections' => 2,
        ]);
        $response->assertJsonCount(2, 'rejections');
        $response->assertJsonPath('rejection_breakdown.0.reason_code', 'BLURRY_PHOTO');
        $response->assertJsonPath('rejection_breakdown.0.count', 2);
        $response->assertJsonPath('rejections.0.note', 'Same issue again.');
    }

    public function test_returns_empty_for_an_agent_with_no_rejections(): void
    {
        $response = $this->getJson('/api/qa/agents/AGT-CLEAN/rejection-feedback');

        $response->assertOk();
        $response->assertJson([
            'agent_id' => 'AGT-CLEAN',
            'total_rejections' => 0,
            'rejection_breakdown' => [],
            'rejections' => [],
        ]);
    }
}
