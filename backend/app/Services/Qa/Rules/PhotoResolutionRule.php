<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * "Resolution (Mandatory)" — the image must contain enough pixels for OCR
 * and product recognition. Only the hard minimum (1280x720) is a fail; the
 * 1920x1080 "recommended" bar is informational only (surfaced to the
 * frontend via QaPresenter, not itself a flag) — a submission shouldn't be
 * auto-failed just for falling short of "recommended".
 */
class PhotoResolutionRule implements QaRule
{
    public const MIN_WIDTH = 1280;

    public const MIN_HEIGHT = 720;

    public const RECOMMENDED_WIDTH = 1920;

    public const RECOMMENDED_HEIGHT = 1080;

    /**
     * Orientation-independent: phones are most naturally held portrait, so
     * a perfectly good "720p-equivalent" photo is just as often 720x1280 as
     * 1280x720. Comparing raw width/height against a landscape-shaped
     * minimum would hard-fail every legitimate portrait shot on the short
     * axis alone. Compare the long edge and short edge instead, regardless
     * of which dimension is which.
     */
    public static function isTooSmall(?int $width, ?int $height): ?bool
    {
        if ($width === null || $height === null) {
            return null;
        }

        $longEdge = max($width, $height);
        $shortEdge = min($width, $height);

        return $longEdge < self::MIN_WIDTH || $shortEdge < self::MIN_HEIGHT;
    }

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        foreach ($submission->media as $media) {
            if ($media->image_width === null || $media->image_height === null) {
                continue;
            }

            $width = (int) $media->image_width;
            $height = (int) $media->image_height;

            if (self::isTooSmall($width, $height)) {
                $flags[] = [
                    'rule_name' => 'low_photo_resolution',
                    'result' => QaFlagResult::Fail,
                    'severity' => 'high',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'image_width' => $width,
                        'image_height' => $height,
                        'min_width' => self::MIN_WIDTH,
                        'min_height' => self::MIN_HEIGHT,
                    ],
                ];
            }
        }

        return $flags;
    }
}
