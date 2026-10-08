<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * "Contrast" — standard deviation of grayscale pixel intensity (RMS
 * contrast), computed by the image-service. Low contrast makes text
 * difficult to read even when it's otherwise sharp and well-lit.
 */
class PhotoContrastRule implements QaRule
{
    public const POOR_THRESHOLD = 25.0;

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        foreach ($submission->media as $media) {
            if ($media->contrast_std_dev === null) {
                continue;
            }

            $stdDev = (float) $media->contrast_std_dev;

            if ($stdDev < self::POOR_THRESHOLD) {
                $flags[] = [
                    'rule_name' => 'low_photo_contrast',
                    'result' => QaFlagResult::Flag,
                    'severity' => 'medium',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'contrast_std_dev' => round($stdDev, 1),
                        'poor_threshold' => self::POOR_THRESHOLD,
                    ],
                ];
            }
        }

        return $flags;
    }
}
