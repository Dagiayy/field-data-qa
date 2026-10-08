<?php

namespace App\Services\Qa\Rules;

use App\Models\Submission;
use App\Services\Qa\AgentAnswerPatternAnalyzer;
use App\Services\Qa\QaRule;

/**
 * Answer Pattern Validation Rule:
 * Detects repetitive, low-effort responses by checking whether THIS
 * submission's own answer to a question is itself part of a real
 * consecutive streak of identical answers from the same agent, on the same
 * quest, ending at this submission (e.g. an agent who has now answered
 * "No" 5+ times in a row regardless of outlet context). Quest structure is
 * dynamic and configurable, so nothing here assumes a fixed question set or
 * fixed answer options — comparisons only ever happen between answers to
 * the exact same question_id within the exact same quest_id.
 *
 * Anchoring on the current submission's own answer (rather than the
 * agent's aggregate historical ratio over a rolling window) means an agent
 * who breaks the pattern with a different, legitimate answer this time is
 * never flagged just because of old history — and a question with few
 * valid options can't trip this by chance the way a bare ratio threshold
 * could.
 */
class AnswerPatternRule implements QaRule
{
    private const LOOKBACK_PER_QUEST = 20;

    public function __construct(private AgentAnswerPatternAnalyzer $analyzer)
    {
    }

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        if (! $submission->agent_id) {
            return $flags;
        }

        $streaks = $this->analyzer->currentSubmissionStreaks($submission, self::LOOKBACK_PER_QUEST);

        foreach ($streaks as $row) {
            $classification = AgentAnswerPatternAnalyzer::classifyStreak($row['streak_length']);
            if ($classification === null) {
                continue;
            }

            $flags[] = [
                'rule_name' => 'answer_pattern_repetitive',
                'result' => $classification['result'],
                'severity' => $classification['severity'],
                'detail' => [
                    'question_id' => $row['question_id'],
                    'agent_id' => $submission->agent_id,
                    'dominant_value' => $row['dominant_value'],
                    'consecutive_repetitions' => $row['streak_length'],
                    'note' => sprintf(
                        "Agent %s has answered '%s' on %d consecutive submissions for this quest question, including this one. Potential low-effort pattern.",
                        $submission->agent_id,
                        $row['dominant_value'],
                        $row['streak_length']
                    ),
                ],
            ];
        }

        return $flags;
    }
}
