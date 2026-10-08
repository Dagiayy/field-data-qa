<?php

namespace Tests\Feature\Qa;

use App\Enums\QaReviewDecision;
use App\Enums\SubmissionStatus;
use App\Models\QaReview;
use App\Models\RejectionReason;
use App\Models\SubmissionTrustScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class AgentTrustScoreControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    private function scoreSubmission(string $submissionId, float $total): void
    {
        SubmissionTrustScore::create([
            'submission_id' => $submissionId,
            'gps_score' => $total,
            'photo_score' => $total,
            'time_score' => $total,
            'completeness_score' => $total,
            'audit_confirmation_score' => $total,
            'total_score' => $total,
            'weights_used' => ['gps' => 0.2, 'photo' => 0.2, 'time' => 0.2, 'completeness' => 0.2, 'audit_confirmation' => 0.2],
        ]);
    }

    public function test_returns_the_agents_real_average_trust_score_and_tier(): void
    {
        $quest = $this->makeQuest('QST-ATS-001');
        $outlet = $this->makeOutlet('OUT-ATS-001');

        $sub1 = $this->makeSubmission($quest, $outlet, 'AGT-GOLD', 'q1', 'yes', now()->subDay());
        $sub2 = $this->makeSubmission($quest, $outlet, 'AGT-GOLD', 'q1', 'yes', now());
        $this->scoreSubmission($sub1->id, 88.0);
        $this->scoreSubmission($sub2->id, 96.0);

        $response = $this->getJson('/api/agents/AGT-GOLD/trust-score');

        $response->assertOk();
        $response->assertJson([
            'agent_id' => 'AGT-GOLD',
            'overall_trust_score' => 92.0,
            'tier' => 'Gold',
        ]);
        $this->assertCount(2, $response->json('recent_builds'));
    }

    public function test_approval_rate_only_counts_decided_submissions(): void
    {
        $quest = $this->makeQuest('QST-ATS-002');
        $outlet = $this->makeOutlet('OUT-ATS-002');

        $approved = $this->makeSubmission($quest, $outlet, 'AGT-RATE', 'q1', 'yes', now()->subDays(3));
        $approved->update(['status' => SubmissionStatus::Approved]);

        $rejected = $this->makeSubmission($quest, $outlet, 'AGT-RATE', 'q1', 'yes', now()->subDays(2));
        $rejected->update(['status' => SubmissionStatus::Rejected]);

        // Still pending review — must not count toward the rate at all.
        $this->makeSubmission($quest, $outlet, 'AGT-RATE', 'q1', 'yes', now()->subDay());

        $response = $this->getJson('/api/agents/AGT-RATE/trust-score');

        $response->assertOk();
        $response->assertJson(['approval_rate' => 50.0]);
    }

    public function test_backcheck_pass_rate_from_recorded_outcomes(): void
    {
        $quest = $this->makeQuest('QST-ATS-003');
        $outlet = $this->makeOutlet('OUT-ATS-003');

        $sub1 = $this->makeSubmission($quest, $outlet, 'AGT-BACKCHECK', 'q1', 'yes', now()->subDay());
        $sub2 = $this->makeSubmission($quest, $outlet, 'AGT-BACKCHECK', 'q1', 'yes', now());

        QaReview::create([
            'submission_id' => $sub1->id,
            'decision' => QaReviewDecision::Approve,
            'backcheck_outcome' => 'confirmed',
            'reviewed_at' => now(),
        ]);
        QaReview::create([
            'submission_id' => $sub2->id,
            'decision' => QaReviewDecision::Reject,
            'backcheck_outcome' => 'not_confirmed',
            'reviewed_at' => now(),
        ]);

        $response = $this->getJson('/api/agents/AGT-BACKCHECK/trust-score');

        $response->assertOk();
        $response->assertJson(['backcheck_pass_rate' => 50.0]);
    }

    public function test_backcheck_pass_rate_defaults_to_100_with_no_backchecks_recorded(): void
    {
        $response = $this->getJson('/api/agents/AGT-NO-BACKCHECK/trust-score');

        $response->assertOk();
        $response->assertJson(['backcheck_pass_rate' => 100.0]);
    }

    public function test_rejection_breakdown_reflects_real_rejection_reasons(): void
    {
        $quest = $this->makeQuest('QST-ATS-004');
        $outlet = $this->makeOutlet('OUT-ATS-004');
        $reason = RejectionReason::create(['code' => 'BLURRY_PHOTO', 'label' => 'Photo Unclear or Obstructed', 'active' => true]);

        $sub = $this->makeSubmission($quest, $outlet, 'AGT-REASONS', 'q1', 'yes', now());
        QaReview::create([
            'submission_id' => $sub->id,
            'decision' => QaReviewDecision::Reject,
            'reason_code' => $reason->id,
            'reviewed_at' => now(),
        ]);

        $response = $this->getJson('/api/agents/AGT-REASONS/trust-score');

        $response->assertOk();
        $response->assertJsonPath('rejection_breakdown.0.reason_code', 'BLURRY_PHOTO');
        $response->assertJsonPath('rejection_breakdown.0.count', 1);
    }

    public function test_returns_sensible_defaults_for_an_agent_with_no_history(): void
    {
        $response = $this->getJson('/api/agents/AGT-BRAND-NEW/trust-score');

        $response->assertOk();
        $response->assertJson([
            'agent_id' => 'AGT-BRAND-NEW',
            'overall_trust_score' => null,
            'approval_rate' => 100.0,
            'backcheck_pass_rate' => 100.0,
            'tier' => 'Bronze',
            'rejection_breakdown' => [],
            'recent_builds' => [],
        ]);
    }
}
