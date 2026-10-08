<?php

namespace Tests\Feature\Qa;

use App\Enums\QaFlagResult;
use App\Enums\QaReviewDecision;
use App\Enums\SubmissionStatus;
use App\Models\PaymentStatus;
use App\Models\QaReview;
use App\Models\RejectionReason;
use App\Models\SubmissionTrustScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class ReportsOverviewTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    public function test_overview_aggregates_queue_quality_trust_and_payment_metrics_correctly(): void
    {
        $questA = $this->makeQuest('QST-REPORT-A');
        $outletA = $this->makeOutlet('OUT-REPORT-A');
        $outletA->update(['city' => 'Addis Ababa']);

        $questB = $this->makeQuest('QST-REPORT-B');
        $outletB = $this->makeOutlet('OUT-REPORT-B');
        $outletB->update(['city' => 'Hawassa']);

        $reviewer = User::factory()->create(['name' => 'Jane Reviewer']);
        $reason = RejectionReason::create([
            'code' => 'PRICE_OUTLIER',
            'label' => 'Price Outlier',
            'applies_to_quest_types' => ['quest'],
            'active' => true,
        ]);

        $now = Carbon::parse('2026-07-25T10:00:00+00:00');

        // Sub1: quest A, approved, Gold tier, paid.
        $sub1 = $this->makeSubmission($questA, $outletA, 'AGT-1', 'q1', 'yes', $now->copy());
        $sub1->update(['status' => SubmissionStatus::Approved]);
        SubmissionTrustScore::create([
            'submission_id' => $sub1->id,
            'gps_score' => 100, 'photo_score' => 100, 'time_score' => 100,
            'completeness_score' => 100, 'audit_confirmation_score' => 100,
            'total_score' => 95, 'weights_used' => [],
        ]);
        QaReview::create([
            'submission_id' => $sub1->id,
            'reviewer_id' => $reviewer->id,
            'decision' => QaReviewDecision::Approve,
            'reviewed_at' => $now->copy()->addMinutes(2),
        ]);
        PaymentStatus::create([
            'submission_id' => $sub1->id, 'agent_id' => 'AGT-1',
            'wallet_state' => 'paid', 'amount' => 100, 'currency' => 'ETB',
        ]);

        // Sub2: quest A, rejected, Flagged tier, rejected payment.
        $sub2 = $this->makeSubmission($questA, $outletA, 'AGT-2', 'q1', 'yes', $now->copy());
        $sub2->update(['status' => SubmissionStatus::Rejected]);
        SubmissionTrustScore::create([
            'submission_id' => $sub2->id,
            'gps_score' => 50, 'photo_score' => 50, 'time_score' => 50,
            'completeness_score' => 50, 'audit_confirmation_score' => 50,
            'total_score' => 50, 'weights_used' => [],
        ]);
        QaReview::create([
            'submission_id' => $sub2->id,
            'reviewer_id' => $reviewer->id,
            'decision' => QaReviewDecision::Reject,
            'reason_code' => $reason->id,
            'reviewed_at' => $now->copy()->addMinutes(5),
        ]);
        PaymentStatus::create([
            'submission_id' => $sub2->id, 'agent_id' => 'AGT-2',
            'wallet_state' => 'rejected', 'amount' => 0, 'currency' => 'ETB',
        ]);

        // Sub3: quest B, still pending, Silver tier, one fail flag, pending payment.
        $sub3 = $this->makeSubmission($questB, $outletB, 'AGT-3', 'q1', 'yes', $now->copy());
        SubmissionTrustScore::create([
            'submission_id' => $sub3->id,
            'gps_score' => 80, 'photo_score' => 80, 'time_score' => 80,
            'completeness_score' => 80, 'audit_confirmation_score' => 80,
            'total_score' => 80, 'weights_used' => [],
        ]);
        $this->makeFlag($sub3, 'low_photo_resolution', QaFlagResult::Fail);
        PaymentStatus::create([
            'submission_id' => $sub3->id, 'agent_id' => 'AGT-3',
            'wallet_state' => 'pending', 'amount' => 50, 'currency' => 'ETB',
        ]);

        // Sub4: quest B, flagged for backcheck, Bronze tier.
        $sub4 = $this->makeSubmission($questB, $outletB, 'AGT-4', 'q1', 'yes', $now->copy());
        $sub4->update(['status' => SubmissionStatus::Backcheck]);
        SubmissionTrustScore::create([
            'submission_id' => $sub4->id,
            'gps_score' => 70, 'photo_score' => 70, 'time_score' => 70,
            'completeness_score' => 70, 'audit_confirmation_score' => 70,
            'total_score' => 70, 'weights_used' => [],
        ]);

        $response = $this->getJson('/api/qa/reports/overview');

        $response->assertOk();
        $json = $response->json();

        $this->assertSame(2, $json['queue_depth']); // sub3 (pending) + sub4 (backcheck)
        $this->assertSame(1, $json['queue_by_status']['pending_review']);
        $this->assertSame(1, $json['queue_by_status']['backcheck']);
        $this->assertSame(0, $json['queue_by_status']['sent_back']);

        $this->assertEquals(50.0, $json['approval_rate']); // 1 approved / 2 decided
        $this->assertEquals(50.0, $json['rejection_rate']);
        $this->assertEquals(33.3, $json['backcheck_rate']); // 1 backcheck / (2 decided + 1 backcheck)

        $this->assertEquals(73.8, $json['avg_trust_score']); // (95+50+80+70)/4 = 73.75 -> 73.8

        $tiers = collect($json['trust_tier_distribution'])->pluck('count', 'tier');
        $this->assertSame(1, $tiers['Gold']); // 95
        $this->assertSame(1, $tiers['Silver']); // 80
        $this->assertSame(1, $tiers['Bronze']); // 70
        $this->assertSame(1, $tiers['Flagged']); // 50

        $byQuest = collect($json['approval_rate_by_quest'])->keyBy('quest_title');
        $this->assertSame(2, $byQuest['Test Quest QST-REPORT-A']['total']);
        $this->assertEquals(50.0, $byQuest['Test Quest QST-REPORT-A']['approval_rate']);
        $this->assertFalse($byQuest->has('Test Quest QST-REPORT-B')); // no decided submissions yet

        $byCity = collect($json['approval_rate_by_city'])->keyBy('city');
        $this->assertSame(2, $byCity['Addis Ababa']['total']);
        $this->assertEquals(50.0, $byCity['Addis Ababa']['approval_rate']);

        $throughput = collect($json['reviewer_throughput'])->firstWhere('reviewer_name', 'Jane Reviewer');
        $this->assertSame(2, $throughput['reviewed_count']);
        $this->assertEquals(3.5, $throughput['avg_decision_time_minutes']); // avg(2, 5)

        $topFlag = collect($json['top_qa_flags'])->firstWhere('rule_name', 'low_photo_resolution');
        $this->assertSame(1, $topFlag['count']);
        $this->assertSame('fail', $topFlag['result']);

        $rejection = collect($json['rejection_breakdown'])->firstWhere('reason_code', 'PRICE_OUTLIER');
        $this->assertSame(1, $rejection['count']);
        $this->assertSame('Price Outlier', $rejection['label']);

        $funnel = collect($json['payment_funnel'])->keyBy('wallet_state');
        $this->assertSame(1, $funnel['paid']['count']);
        $this->assertEquals(100.0, $funnel['paid']['total_amount']);
        $this->assertSame(1, $funnel['pending']['count']);
        $this->assertSame(1, $funnel['rejected']['count']);
    }

    public function test_overview_returns_nulls_and_empty_collections_when_no_data_exists(): void
    {
        $response = $this->getJson('/api/qa/reports/overview');

        $response->assertOk();
        $json = $response->json();

        $this->assertSame(0, $json['queue_depth']);
        $this->assertNull($json['oldest_pending_age_hours']);
        $this->assertNull($json['avg_time_in_queue_minutes']);
        $this->assertNull($json['avg_trust_score']);
        $this->assertNull($json['approval_rate']);
        $this->assertNull($json['rejection_rate']);
        $this->assertNull($json['backcheck_rate']);
        $this->assertEmpty($json['approval_rate_by_quest']);
        $this->assertEmpty($json['reviewer_throughput']);
        $this->assertEmpty($json['top_qa_flags']);
    }

    public function test_overview_respects_the_date_range_filter(): void
    {
        $quest = $this->makeQuest('QST-REPORT-C');
        $outlet = $this->makeOutlet('OUT-REPORT-C');

        $inRange = $this->makeSubmission($quest, $outlet, 'AGT-5', 'q1', 'yes', Carbon::parse('2026-07-25T09:00:00+00:00'));
        $outOfRange = $this->makeSubmission($quest, $outlet, 'AGT-6', 'q1', 'yes', Carbon::parse('2026-06-01T09:00:00+00:00'));

        $response = $this->getJson('/api/qa/reports/overview?date_from=2026-07-20&date_to=2026-07-26');

        $response->assertOk();
        $json = $response->json();

        $this->assertSame(1, $json['queue_depth']);
    }
}
