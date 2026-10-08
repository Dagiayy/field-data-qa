<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * "Size/framing: the product must be captured at a usable size in the
 * frame." Proxy: tallest OCR-detected text block's height, relative to the
 * photo's height. This is deliberately a 3-state check, not pass/fail —
 * per the spec, a submission right at or below the acceptable threshold
 * gets flagged for human review, not auto-failed; only a size that's
 * unambiguously too small to be usable is a hard fail.
 */
class PhotoSizeFramingRule implements QaRule
{
    public const HARD_FAIL_RATIO = 0.015;

    public const BORDERLINE_RATIO = 0.04;

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        foreach ($submission->media as $media) {
            if ($media->text_height_ratio === null) {
                continue;
            }

            $ratio = (float) $media->text_height_ratio;

            if ($ratio < self::HARD_FAIL_RATIO) {
                $flags[] = [
                    'rule_name' => 'small_product_in_frame',
                    'result' => QaFlagResult::Fail,
                    'severity' => 'medium',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'text_height_ratio' => round($ratio, 4),
                        'hard_fail_ratio' => self::HARD_FAIL_RATIO,
                        'note' => 'Product/label is too small in frame to be usable.',
                    ],
                ];
            } elseif ($ratio < self::BORDERLINE_RATIO) {
                $flags[] = [
                    'rule_name' => 'small_product_in_frame',
                    'result' => QaFlagResult::Flag,
                    'severity' => 'low',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'text_height_ratio' => round($ratio, 4),
                        'borderline_ratio' => self::BORDERLINE_RATIO,
                        'note' => 'At or below the acceptable size threshold — routed to human review rather than auto-passed or auto-failed.',
                    ],
                ];
            }
        }

        return $flags;
    }
}
