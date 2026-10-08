<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * "Brightness" — mean grayscale luminance (0-255) from the image-service.
 * Same thresholds it uses so the two never drift apart.
 */
class PhotoExposureRule implements QaRule
{
    public const DARK_THRESHOLD = 50.0;

    public const OVEREXPOSED_THRESHOLD = 220.0;

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        foreach ($submission->media as $media) {
            if ($media->brightness_mean === null) {
                continue;
            }

            $brightness = (float) $media->brightness_mean;
            $isDark = $brightness < self::DARK_THRESHOLD;
            $isOverexposed = $brightness > self::OVEREXPOSED_THRESHOLD;

            if ($isDark || $isOverexposed) {
                $flags[] = [
                    'rule_name' => 'poor_photo_lighting',
                    'result' => QaFlagResult::Flag,
                    'severity' => 'medium',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'issue' => $isDark ? 'too_dark' : 'overexposed',
                        'brightness_mean' => round($brightness, 1),
                    ],
                ];
            }
        }

        return $flags;
    }
}
