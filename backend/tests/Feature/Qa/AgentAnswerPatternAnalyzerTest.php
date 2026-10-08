<?php

namespace Tests\Feature\Qa;

use App\Enums\QaFlagResult;
use App\Services\Qa\AgentAnswerPatternAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class AgentAnswerPatternAnalyzerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    public function test_keeps_the_same_question_id_separate_across_different_quests(): void
    {
        $questA = $this->makeQuest('QST-TEST-A');
        $questB = $this->makeQuest('QST-TEST-B');
        $outlet = $this->makeOutlet('OUT-TEST-A');
        $agentId = 'AGT-CROSS-QUEST';

        // Quest A: agent always answers "no" for q1_generic.
        for ($i = 0; $i < 6; $i++) {
            $this->makeSubmission($questA, $outlet, $agentId, 'q1_generic', 'no', now()->subDays(20 - $i));
        }

        // Quest B reuses the same question_id string for something
        // unrelated, and the agent's answers there are genuinely varied.
        foreach (['yes', 'no', 'yes', 'no', 'yes', 'no'] as $i => $value) {
            $this->makeSubmission($questB, $outlet, $agentId, 'q1_generic', $value, now()->subDays(10 - $i));
        }

        $breakdown = (new AgentAnswerPatternAnalyzer)->breakdownForAgent($agentId);

        $rows = $breakdown->where('question_id', 'q1_generic');
        $this->assertCount(2, $rows);

        $questARow = $rows->firstWhere('quest_id', $questA->id);
        $questBRow = $rows->firstWhere('quest_id', $questB->id);

        $this->assertSame(1.0, $questARow['ratio']);
        $this->assertSame(0.5, $questBRow['ratio']);
    }

    public function test_classify_thresholds(): void
    {
        $this->assertNull(AgentAnswerPatternAnalyzer::classify(0.84));

        $flag = AgentAnswerPatternAnalyzer::classify(0.85);
        $this->assertSame(QaFlagResult::Flag, $flag['result']);
        $this->assertSame('medium', $flag['severity']);

        $fail = AgentAnswerPatternAnalyzer::classify(0.95);
        $this->assertSame(QaFlagResult::Fail, $fail['result']);
        $this->assertSame('high', $fail['severity']);
    }
}
