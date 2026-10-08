<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * "Blur / Sharpness" — variance of the Laplacian (OpenCV), computed by the
 * image-service. One of the biggest causes of OCR failure. Thresholds are
 * the spec's suggested starting points and should be recalibrated against a
 * real dataset from this deployment's cameras.
 */
class PhotoBlurRule implements QaRule
{
    public const HARD_FAIL_VARIANCE = 80.0;

    public const BORDERLINE_VARIANCE = 150.0;

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        foreach ($submission->media as $media) {
            if ($media->sharpness_laplacian_var === null) {
                continue;
            }

            $variance = (float) $media->sharpness_laplacian_var;

            if ($variance < self::HARD_FAIL_VARIANCE) {
                $flags[] = [
                    'rule_name' => 'photo_too_blurry',
                    'result' => QaFlagResult::Fail,
                    'severity' => 'high',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'sharpness_laplacian_var' => round($variance, 1),
                        'hard_fail_variance' => self::HARD_FAIL_VARIANCE,
                        'note' => 'Very blurry — reject.',
                    ],
                ];
            } elseif ($variance < self::BORDERLINE_VARIANCE) {
                $flags[] = [
                    'rule_name' => 'photo_too_blurry',
                    'result' => QaFlagResult::Flag,
                    'severity' => 'medium',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'sharpness_laplacian_var' => round($variance, 1),
                        'borderline_variance' => self::BORDERLINE_VARIANCE,
                        'note' => 'Slightly blurry — needs human review.',
                    ],
                ];
            }
        }

        return $flags;
    }
}
