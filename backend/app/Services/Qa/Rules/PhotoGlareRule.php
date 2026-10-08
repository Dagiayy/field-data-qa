<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * "Glare / Reflection" — percentage of near-fully-saturated pixels
 * (>=250/255), computed by the image-service. Glossy packaging often
 * creates bright reflections that obscure text.
 */
class PhotoGlareRule implements QaRule
{
    public const WARNING_RATIO_PCT = 2.0;

    public const REJECT_RATIO_PCT = 5.0;

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        foreach ($submission->media as $media) {
            if ($media->glare_ratio_pct === null) {
                continue;
            }

            $ratio = (float) $media->glare_ratio_pct;

            if ($ratio > self::REJECT_RATIO_PCT) {
                $flags[] = [
                    'rule_name' => 'excessive_photo_glare',
                    'result' => QaFlagResult::Fail,
                    'severity' => 'high',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'glare_ratio_pct' => round($ratio, 2),
                        'reject_threshold_pct' => self::REJECT_RATIO_PCT,
                    ],
                ];
            } elseif ($ratio > self::WARNING_RATIO_PCT) {
                $flags[] = [
                    'rule_name' => 'excessive_photo_glare',
                    'result' => QaFlagResult::Flag,
                    'severity' => 'medium',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'glare_ratio_pct' => round($ratio, 2),
                        'warning_threshold_pct' => self::WARNING_RATIO_PCT,
                    ],
                ];
            }
        }

        return $flags;
    }
}
