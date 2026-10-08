<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * "Noise" — fast noise-variance estimate (Immerkaer's method), computed by
 * the image-service. High-ISO/low-light shots introduce noise that
 * degrades OCR. The spec gives no universal numeric thresholds ("these
 * vary by camera and dataset") — these starting points should be
 * recalibrated once real submissions from this deployment's devices are
 * available.
 */
class PhotoNoiseRule implements QaRule
{
    public const WARNING_SIGMA = 4.0;

    public const REJECT_SIGMA = 10.0;

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        foreach ($submission->media as $media) {
            if ($media->noise_sigma === null) {
                continue;
            }

            $sigma = (float) $media->noise_sigma;

            if ($sigma > self::REJECT_SIGMA) {
                $flags[] = [
                    'rule_name' => 'excessive_photo_noise',
                    'result' => QaFlagResult::Fail,
                    'severity' => 'high',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'noise_sigma' => round($sigma, 2),
                        'reject_threshold' => self::REJECT_SIGMA,
                    ],
                ];
            } elseif ($sigma > self::WARNING_SIGMA) {
                $flags[] = [
                    'rule_name' => 'excessive_photo_noise',
                    'result' => QaFlagResult::Flag,
                    'severity' => 'low',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'noise_sigma' => round($sigma, 2),
                        'warning_threshold' => self::WARNING_SIGMA,
                    ],
                ];
            }
        }

        return $flags;
    }
}
