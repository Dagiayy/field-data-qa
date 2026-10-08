<?php

namespace Tests\Feature\Qa;

use App\Models\SubmissionTrustScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class QueueAgentTrustScoreTest extends TestCase
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

    public function test_queue_trust_score_column_is_the_agents_average_not_the_row_own_score(): void
    {
        $quest = $this->makeQuest('QST-QUEUE-TRUST-001');
        $outlet = $this->makeOutlet('OUT-QUEUE-TRUST-001');

        $sub1 = $this->makeSubmission($quest, $outlet, 'AGT-AVG', 'q1', 'yes', now()->subDay());
        $sub2 = $this->makeSubmission($quest, $outlet, 'AGT-AVG', 'q1', 'yes', now());

        // Same agent, two very different submission-level scores — average is 60.
        $this->scoreSubmission($sub1->id, 40.0);
        $this->scoreSubmission($sub2->id, 80.0);

        $response = $this->getJson('/api/qa/queue?status=pending');

        $response->assertOk();
        $rows = collect($response->json('data'))->keyBy('id');

        $this->assertEquals(40.0, $rows[$sub1->id]['trust_score']['total_score']);
        $this->assertEquals(80.0, $rows[$sub2->id]['trust_score']['total_score']);

        // Both rows show the SAME agent-level figure (the average), even
        // though their own submission-level scores differ.
        $this->assertEquals(60.0, $rows[$sub1->id]['agent_overall_trust_score']);
        $this->assertEquals(60.0, $rows[$sub2->id]['agent_overall_trust_score']);
    }
}
