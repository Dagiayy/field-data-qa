<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * Two independent timing checks, both filed under 'timestamp_consistency'
 * (the name the frontend already knows how to label): a survey completed
 * faster than plausibly possible, and a photo whose capture time predates
 * the survey window (a reused/gallery photo).
 */
class TimestampConsistencyRule implements QaRule
{
    private const MIN_SECONDS_PER_ANSWER = 5;

    private const MIN_DURATION_FLOOR_SECONDS = 30;

    private const STALE_PHOTO_TOLERANCE_HOURS = 24;

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        if ($submission->survey_start_at && $submission->survey_end_at) {
            $durationSeconds = $submission->survey_start_at->diffInSeconds($submission->survey_end_at);
            $expectedMinSeconds = max(
                self::MIN_DURATION_FLOOR_SECONDS,
                $submission->answers->count() * self::MIN_SECONDS_PER_ANSWER,
            );

            if ($durationSeconds < $expectedMinSeconds) {
                $flags[] = [
                    'rule_name' => 'timestamp_consistency',
                    'result' => QaFlagResult::Flag,
                    'severity' => 'medium',
                    'detail' => [
                        'check' => 'survey_too_fast',
                        'duration_seconds' => $durationSeconds,
                        'expected_min_seconds' => $expectedMinSeconds,
                    ],
                ];
            }
        }

        foreach ($submission->media as $media) {
            if (! $media->captured_at || ! $submission->submitted_at) {
                continue;
            }

            if (! $media->captured_at->lessThan($submission->submitted_at)) {
                continue;
            }

            $hoursBeforeSubmission = $media->captured_at->diffInHours($submission->submitted_at);

            if ($hoursBeforeSubmission > self::STALE_PHOTO_TOLERANCE_HOURS) {
                $flags[] = [
                    'rule_name' => 'timestamp_consistency',
                    'result' => QaFlagResult::Fail,
                    'severity' => 'high',
                    'detail' => [
                        'check' => 'stale_photo',
                        'media_ref' => $media->media_ref,
                        'captured_at' => $media->captured_at->toIso8601String(),
                        'submitted_at' => $submission->submitted_at->toIso8601String(),
                        'hours_before_submission' => round($hoursBeforeSubmission, 1),
                    ],
                ];
            }
        }

        return $flags;
    }
}
