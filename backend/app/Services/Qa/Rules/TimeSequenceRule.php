<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * Time Validation, second use of the timestamps: once accepted/start/end/
 * submitted are all known, verify the whole sequence is internally
 * consistent (each step happens no earlier than the one before it) —
 * a cross-check applied once the data has already passed through the
 * system. Separately flags an excessive accept-to-start delay as the
 * behavioral signal meant to feed the Trust Score later.
 *
 * A missing quest_accepted_at (no QuestAcceptance record — see
 * QuestAcceptanceController, /api/quests/{quest}/accept) means the whole
 * accept→start behavioral signal — including the "agent started almost
 * immediately" dedication bonus TrustScoreCalculator can award — was never
 * actually recordable for this submission. That's a real accountability
 * gap (we can't confirm when, or whether, the agent formally accepted this
 * quest before starting), not a neutral "nothing to check" — flagged the
 * same way the equivalent Baseline Management gaps are (see
 * SurveyDurationRule::no_duration_baseline_configured,
 * BaselineLocationRule::no_outlet_baseline_configured), rather than
 * silently skipped.
 */
class TimeSequenceRule implements QaRule
{
    private const MAX_ACCEPT_TO_START_SECONDS = 2 * 60 * 60; // 2 hours

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        $accepted = $submission->quest_accepted_at;
        $start = $submission->survey_start_at;
        $end = $submission->survey_end_at;
        $submitted = $submission->submitted_at;

        if ($accepted && $start && $accepted->greaterThan($start)) {
            $flags[] = [
                'rule_name' => 'time_sequence_inconsistent',
                'result' => QaFlagResult::Fail,
                'severity' => 'high',
                'detail' => [
                    'check' => 'accepted_after_started',
                    'quest_accepted_at' => $accepted->toIso8601String(),
                    'survey_start_at' => $start->toIso8601String(),
                ],
            ];
        }

        if ($start && $end && $start->greaterThan($end)) {
            $flags[] = [
                'rule_name' => 'time_sequence_inconsistent',
                'result' => QaFlagResult::Fail,
                'severity' => 'high',
                'detail' => [
                    'check' => 'started_after_finished',
                    'survey_start_at' => $start->toIso8601String(),
                    'survey_end_at' => $end->toIso8601String(),
                ],
            ];
        }

        if ($end && $submitted && $end->greaterThan($submitted)) {
            $flags[] = [
                'rule_name' => 'time_sequence_inconsistent',
                'result' => QaFlagResult::Fail,
                'severity' => 'high',
                'detail' => [
                    'check' => 'finished_after_submitted',
                    'survey_end_at' => $end->toIso8601String(),
                    'submitted_at' => $submitted->toIso8601String(),
                ],
            ];
        }

        if ($accepted && $start) {
            $acceptToStartSeconds = $accepted->diffInSeconds($start);

            if ($acceptToStartSeconds > self::MAX_ACCEPT_TO_START_SECONDS) {
                $flags[] = [
                    'rule_name' => 'excessive_accept_to_start_delay',
                    'result' => QaFlagResult::Flag,
                    'severity' => 'low',
                    'detail' => [
                        'quest_accepted_at' => $accepted->toIso8601String(),
                        'survey_start_at' => $start->toIso8601String(),
                        'accept_to_start_seconds' => $acceptToStartSeconds,
                        'max_acceptable_seconds' => self::MAX_ACCEPT_TO_START_SECONDS,
                    ],
                ];
            }
        } elseif ($start && ! $accepted) {
            $flags[] = [
                'rule_name' => 'no_quest_acceptance_recorded',
                'result' => QaFlagResult::Flag,
                'severity' => 'low',
                'detail' => [
                    'survey_start_at' => $start->toIso8601String(),
                ],
            ];
        }

        return $flags;
    }
}
