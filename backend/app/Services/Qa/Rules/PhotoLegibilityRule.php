<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * "Text Readability" — OCR confidence (RapidOCR), the requirement being
 * that the product name must actually be legible in the photo. A 3-state
 * check per the spec: <60% reject, 60-80% warning/needs review, >80% good.
 * No detected text at all is treated the same as sub-60% confidence — OCR
 * found literally nothing to read.
 */
class PhotoLegibilityRule implements QaRule
{
    public const MIN_ACCEPTABLE_CONFIDENCE = 60.0;

    public const GOOD_CONFIDENCE = 80.0;

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        foreach ($submission->media as $media) {
            if ($media->ocr_avg_confidence === null) {
                continue;
            }

            $noText = trim((string) $media->ocr_text) === '';
            $confidence = (float) $media->ocr_avg_confidence;

            if ($noText || $confidence < self::MIN_ACCEPTABLE_CONFIDENCE) {
                $flags[] = [
                    'rule_name' => 'low_photo_legibility',
                    'result' => QaFlagResult::Fail,
                    'severity' => 'high',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'ocr_avg_confidence' => round($confidence, 1),
                        'text_detected' => ! $noText,
                        'min_acceptable_confidence' => self::MIN_ACCEPTABLE_CONFIDENCE,
                        'note' => 'OCR could not reliably read the product name — prompt the agent to retake the image.',
                    ],
                ];
            } elseif ($confidence < self::GOOD_CONFIDENCE) {
                $flags[] = [
                    'rule_name' => 'low_photo_legibility',
                    'result' => QaFlagResult::Flag,
                    'severity' => 'medium',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'ocr_avg_confidence' => round($confidence, 1),
                        'text_detected' => true,
                        'good_confidence' => self::GOOD_CONFIDENCE,
                    ],
                ];
            }
        }

        return $flags;
    }
}
