<?php

namespace Tests\Feature\Qa;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class AgentAnswerPatternControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    public function test_returns_flagged_question_breakdown_for_an_agent(): void
    {
        $quest = $this->makeQuest('QST-TEST-010');
        $outlet = $this->makeOutlet('OUT-TEST-010');
        $agentId = 'AGT-DASHBOARD';

        for ($i = 0; $i < 7; $i++) {
            $this->makeSubmission($quest, $outlet, $agentId, 'q3_available', 'no', now()->subDays(10 - $i));
        }

        $response = $this->getJson("/api/qa/agents/{$agentId}/answer-patterns");

        $response->assertOk();
        $response->assertJson([
            'agent_id' => $agentId,
            'questions_evaluated' => 1,
            'questions_flagged' => 1,
        ]);

        $quests = $response->json('quests');
        $this->assertSame($quest->form_code, $quests[0]['quest_id']);
        $this->assertSame('q3_available', $quests[0]['questions'][0]['question_id']);
        $this->assertSame('fail', $quests[0]['questions'][0]['result']);
        $this->assertEquals(100.0, $quests[0]['questions'][0]['repetition_ratio']);
    }

    public function test_returns_empty_breakdown_for_agent_with_no_history(): void
    {
        $response = $this->getJson('/api/qa/agents/AGT-UNKNOWN/answer-patterns');

        $response->assertOk();
        $response->assertJson([
            'agent_id' => 'AGT-UNKNOWN',
            'questions_evaluated' => 0,
            'questions_flagged' => 0,
            'worst_repetition_ratio' => null,
            'quests' => [],
        ]);
    }
}
