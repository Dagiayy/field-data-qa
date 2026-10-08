<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * Checks the actual survey_start_at → survey_end_at duration against the
 * quest's admin-configured expected range (Baseline Management —
 * Quest::expected_duration_min_seconds/max_seconds). Unlike TimeSequenceRule
 * (which only checks that the accepted/start/end/submitted timestamps are in
 * the right ORDER), this checks the actual duration MAGNITUDE against a real
 * baseline.
 *
 * A quest with no duration baseline configured yet is flagged as needing one
 * set up — not silently skipped as "nothing to check" and not treated as a
 * pass. This mirrors how a missing OutletBaseline GPS point is already
 * treated (see BaselineLocationRule / the GPS section of SubmissionReview).
 */
class SurveyDurationRule implements QaRule
{
    public function evaluate(Submission $submission): array
    {
        $start = $submission->survey_start_at;
        $end = $submission->survey_end_at;

        if (! $start || ! $end || $end->lessThan($start)) {
            // Missing or already-inconsistent timestamps are TimeSequenceRule's
            // job to flag — this rule only judges duration magnitude once it
            // has a valid, orderable start/end pair to measure.
            return [];
        }

        $quest = $submission->quest;
        if (! $quest) {
            return [];
        }

        // Carbon's diffInSeconds is signed here (defaults to $absolute =
        // false): $a->diffInSeconds($b) computes $b minus $a. $start is
        // guaranteed <= $end above, so $start->diffInSeconds($end) is the
        // correct (positive) call order — the reverse would silently
        // return a negative duration and misclassify every submission as
        // impossibly fast.
        $durationSeconds = $start->diffInSeconds($end);

        if (! $quest->hasDurationBaseline()) {
            return [[
                'rule_name' => 'no_duration_baseline_configured',
                'result' => QaFlagResult::Flag,
                'severity' => 'low',
                'detail' => [
                    'quest_id' => $quest->form_code,
                    'actual_duration_seconds' => $durationSeconds,
                ],
            ]];
        }

        $min = $quest->expected_duration_min_seconds;
        $max = $quest->expected_duration_max_seconds;

        $tooFast = $min !== null && $durationSeconds < $min;
        $tooSlow = $max !== null && $durationSeconds > $max;

        if (! $tooFast && ! $tooSlow) {
            return [];
        }

        return [[
            'rule_name' => 'survey_duration_outside_expected_range',
            // Too fast is the stronger fraud signal (rushed/fabricated
            // survey) than running long, which is more often just a slow
            // connection or an interrupted visit.
            'result' => $tooFast ? QaFlagResult::Fail : QaFlagResult::Flag,
            'severity' => $tooFast ? 'high' : 'medium',
            'detail' => [
                'actual_duration_seconds' => $durationSeconds,
                'expected_min_seconds' => $min,
                'expected_max_seconds' => $max,
                'direction' => $tooFast ? 'too_fast' : 'too_slow',
            ],
        ]];
    }
}
