<?php

namespace Tests\Feature\Qa;

use App\Services\Qa\AgentAnswerPatternAnalyzer;
use App\Services\Qa\Rules\AnswerPatternRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class AnswerPatternRuleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    /**
     * @return list<\App\Models\Outlet>
     */
    private function makeOutletPool(string $prefix, int $count): array
    {
        return array_map(fn (int $i) => $this->makeOutlet("{$prefix}-{$i}"), range(1, $count));
    }

    public function test_flags_agent_who_repeats_the_same_answer(): void
    {
        $quest = $this->makeQuest('QST-TEST-001');
        $outlets = $this->makeOutletPool('OUT-TEST-001', 2);
        $agentId = 'AGT-REPEAT';

        // 6 prior + this submission = a 7-long consecutive streak, which
        // sits in the "flag" tier (>= STREAK_FLAG=5, < STREAK_FAIL=8).
        // Alternating outlets proves this is genuinely outlet-independent
        // repetition, not just a fact about one location.
        for ($i = 0; $i < 6; $i++) {
            $this->makeSubmission($quest, $outlets[$i % 2], $agentId, 'q3_available', 'no', now()->subDays(10 - $i));
        }
        $latest = $this->makeSubmission($quest, $outlets[0], $agentId, 'q3_available', 'no', now());

        $flags = (new AnswerPatternRule(new AgentAnswerPatternAnalyzer))->evaluate($latest);

        $this->assertCount(1, $flags);
        $this->assertSame('answer_pattern_repetitive', $flags[0]['rule_name']);
        $this->assertSame('flag', $flags[0]['result']->value);
        $this->assertSame('medium', $flags[0]['severity']);
        $this->assertSame(7, $flags[0]['detail']['consecutive_repetitions']);
    }

    public function test_fails_agent_with_a_long_repetition_streak(): void
    {
        $quest = $this->makeQuest('QST-TEST-001B');
        $outlets = $this->makeOutletPool('OUT-TEST-001B', 2);
        $agentId = 'AGT-REPEAT-LONG';

        // 8 prior + this submission = a 9-long streak, past STREAK_FAIL=8.
        for ($i = 0; $i < 8; $i++) {
            $this->makeSubmission($quest, $outlets[$i % 2], $agentId, 'q3_available', 'no', now()->subDays(10 - $i));
        }
        $latest = $this->makeSubmission($quest, $outlets[0], $agentId, 'q3_available', 'no', now());

        $flags = (new AnswerPatternRule(new AgentAnswerPatternAnalyzer))->evaluate($latest);

        $this->assertCount(1, $flags);
        $this->assertSame('fail', $flags[0]['result']->value);
        $this->assertSame('high', $flags[0]['severity']);
        $this->assertSame(9, $flags[0]['detail']['consecutive_repetitions']);
    }

    public function test_does_not_flag_when_current_answer_breaks_the_streak(): void
    {
        $quest = $this->makeQuest('QST-TEST-001C');
        $outlets = $this->makeOutletPool('OUT-TEST-001C', 2);
        $agentId = 'AGT-BROKE-STREAK';

        // A long "no" history — but the agent answers "yes" this time,
        // genuinely breaking the pattern. The old ratio-based logic flagged
        // this anyway purely from aggregate history; the current answer
        // must not be penalized for a pattern it isn't actually part of.
        for ($i = 0; $i < 7; $i++) {
            $this->makeSubmission($quest, $outlets[$i % 2], $agentId, 'q3_available', 'no', now()->subDays(10 - $i));
        }
        $latest = $this->makeSubmission($quest, $outlets[0], $agentId, 'q3_available', 'yes', now());

        $flags = (new AnswerPatternRule(new AgentAnswerPatternAnalyzer))->evaluate($latest);

        $this->assertCount(0, $flags);
    }

    public function test_does_not_flag_repetition_confined_to_a_single_outlet(): void
    {
        $quest = $this->makeQuest('QST-TEST-001D');
        $outlet = $this->makeOutlet('OUT-TEST-001D');
        $agentId = 'AGT-SAME-OUTLET';

        // The same outlet, over and over (a repeat-visit quest, or simply
        // re-testing the same sample payload) — every answer trivially
        // "repeats" because it's the same real-world facts each time
        // (region, zone, consent, GPS confirmation), not low-effort
        // behavior. Must NOT be flagged: the pattern never crosses outlets.
        for ($i = 0; $i < 8; $i++) {
            $this->makeSubmission($quest, $outlet, $agentId, 'q3_available', 'no', now()->subDays(10 - $i));
        }
        $latest = $this->makeSubmission($quest, $outlet, $agentId, 'q3_available', 'no', now());

        $flags = (new AnswerPatternRule(new AgentAnswerPatternAnalyzer))->evaluate($latest);

        $this->assertCount(0, $flags);
    }

    public function test_does_not_flag_varied_answers(): void
    {
        $quest = $this->makeQuest('QST-TEST-002');
        $outlets = $this->makeOutletPool('OUT-TEST-002', 2);
        $agentId = 'AGT-VARIED';

        $answers = ['yes', 'no', 'yes', 'no', 'yes', 'no'];
        foreach ($answers as $i => $value) {
            $this->makeSubmission($quest, $outlets[$i % 2], $agentId, 'q3_available', $value, now()->subDays(10 - $i));
        }
        $latest = $this->makeSubmission($quest, $outlets[0], $agentId, 'q3_available', 'yes', now());

        $flags = (new AnswerPatternRule(new AgentAnswerPatternAnalyzer))->evaluate($latest);

        $this->assertCount(0, $flags);
    }

    public function test_requires_minimum_sample_before_flagging(): void
    {
        $quest = $this->makeQuest('QST-TEST-003');
        $outlet = $this->makeOutlet('OUT-TEST-003');
        $agentId = 'AGT-NEW';

        for ($i = 0; $i < 2; $i++) {
            $this->makeSubmission($quest, $outlet, $agentId, 'q3_available', 'no', now()->subDays(2 - $i));
        }
        $latest = $this->makeSubmission($quest, $outlet, $agentId, 'q3_available', 'no', now());

        $flags = (new AnswerPatternRule(new AgentAnswerPatternAnalyzer))->evaluate($latest);

        $this->assertCount(0, $flags);
    }

    public function test_ignores_free_form_input_types_like_text_and_numeric_price(): void
    {
        $quest = $this->makeQuest('QST-TEST-004');
        $outlet = $this->makeOutlet('OUT-TEST-004');
        $agentId = 'AGT-FREEFORM';

        for ($i = 0; $i < 6; $i++) {
            $this->makeSubmission($quest, $outlet, $agentId, 'q9_note', 'same note every time', now()->subDays(10 - $i), 'text');
        }
        $latest = $this->makeSubmission($quest, $outlet, $agentId, 'q9_note', 'same note every time', now(), 'text');

        $flags = (new AnswerPatternRule(new AgentAnswerPatternAnalyzer))->evaluate($latest);

        $this->assertCount(0, $flags);
    }

    public function test_does_not_flag_a_different_agent_sharing_the_same_quest(): void
    {
        $quest = $this->makeQuest('QST-TEST-005');
        $outlet = $this->makeOutlet('OUT-TEST-005');

        for ($i = 0; $i < 6; $i++) {
            $this->makeSubmission($quest, $outlet, 'AGT-OTHER', 'q3_available', 'no', now()->subDays(10 - $i));
        }

        $answers = ['yes', 'no', 'yes', 'no', 'yes'];
        foreach ($answers as $i => $value) {
            $this->makeSubmission($quest, $outlet, 'AGT-CLEAN', 'q3_available', $value, now()->subDays(5 - $i));
        }
        $latest = $this->makeSubmission($quest, $outlet, 'AGT-CLEAN', 'q3_available', 'yes', now());

        $flags = (new AnswerPatternRule(new AgentAnswerPatternAnalyzer))->evaluate($latest);

        $this->assertCount(0, $flags);
    }
}
