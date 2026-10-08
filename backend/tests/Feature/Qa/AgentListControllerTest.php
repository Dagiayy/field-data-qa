<?php

namespace Tests\Feature\Qa;

use App\Enums\SubmissionStatus;
use App\Models\SubmissionTrustScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class AgentListControllerTest extends TestCase
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

    public function test_lists_every_agent_with_real_aggregate_figures_sorted_by_most_recent(): void
    {
        $quest = $this->makeQuest('QST-LIST-001');
        $outlet = $this->makeOutlet('OUT-LIST-001');

        $olderSub = $this->makeSubmission($quest, $outlet, 'AGT-OLDER', 'q1', 'yes', now()->subDays(5));
        $olderSub->update(['status' => SubmissionStatus::Approved]);
        $this->scoreSubmission($olderSub->id, 90.0);

        $newerSub1 = $this->makeSubmission($quest, $outlet, 'AGT-NEWER', 'q1', 'yes', now()->subDays(2));
        $newerSub1->update(['status' => SubmissionStatus::Approved]);
        $this->scoreSubmission($newerSub1->id, 80.0);

        $newerSub2 = $this->makeSubmission($quest, $outlet, 'AGT-NEWER', 'q1', 'yes', now());
        $newerSub2->update(['status' => SubmissionStatus::Rejected]);
        $this->scoreSubmission($newerSub2->id, 40.0);

        $response = $this->getJson('/api/agents');

        $response->assertOk();
        $data = collect($response->json('data'));

        // Most recently active agent first.
        $this->assertSame('AGT-NEWER', $data->first()['agent_id']);
        $this->assertSame('AGT-OLDER', $data->last()['agent_id']);

        $newer = $data->firstWhere('agent_id', 'AGT-NEWER');
        $this->assertEquals(60.0, $newer['overall_trust_score']); // avg(80, 40)
        $this->assertEquals(50.0, $newer['approval_rate']); // 1 approved / 2 decided
        $this->assertSame(2, $newer['total_submissions']);

        $older = $data->firstWhere('agent_id', 'AGT-OLDER');
        $this->assertEquals(90.0, $older['overall_trust_score']);
        $this->assertSame('Gold', $older['tier']);
    }

    public function test_returns_empty_list_when_no_submissions_exist(): void
    {
        $response = $this->getJson('/api/agents');

        $response->assertOk();
        $response->assertJson(['data' => []]);
    }
}
