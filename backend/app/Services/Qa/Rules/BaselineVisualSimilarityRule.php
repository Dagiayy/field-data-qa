<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\ImageSimilarity\ImageEmbeddingService;
use App\Services\ImageSimilarity\ImageSimilarityService;
use App\Services\Qa\QaRule;

/**
 * "Base photo comparison" — every submitted photo already gets compared to
 * its matched OutletBaseline reference photo (see BaselineLocationRule,
 * which resolves the match and caches both the hash and the OpenCLIP
 * embedding). This rule turns that comparison into an actual flag.
 *
 * Primary signal is vision-embedding cosine similarity (semantic —
 * tolerant of ordinary real-world variation like lighting or a restocked
 * shelf, while still catching "this is clearly a different spot/product").
 * Falls back to the cruder perceptual-hash distance only if the
 * image-service was unreachable and no embedding was computed.
 *
 * Deliberately Flag-only, never Fail: this is a nudge toward the human
 * reviewer's side-by-side check, not a replacement for it — visual
 * similarity alone can't reliably tell "wrong product" apart from ordinary
 * real-world variation.
 */
class BaselineVisualSimilarityRule implements QaRule
{
    public const EMBEDDING_FLAG_BELOW = 0.75;

    public const EMBEDDING_HIGH_SEVERITY_BELOW = 0.60;

    private const HASH_BITS = 64;

    private const HASH_FLAG_DISTANCE = 35;

    private const HASH_HIGH_SEVERITY_DISTANCE = 45;

    public function __construct(
        private ImageSimilarityService $imageSimilarity,
        private ImageEmbeddingService $imageEmbedding,
    ) {
    }

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        foreach ($submission->media as $media) {
            $baseline = $media->matchedBaseline;
            if ($baseline === null) {
                continue;
            }

            if ($media->embedding !== null && $baseline->baseline_embedding !== null) {
                $similarity = $this->imageEmbedding->cosineSimilarity($media->embedding, $baseline->baseline_embedding);
                if ($similarity === null) {
                    continue;
                }

                if ($similarity < self::EMBEDDING_FLAG_BELOW) {
                    $flags[] = [
                        'rule_name' => 'baseline_photo_mismatch',
                        'result' => QaFlagResult::Flag,
                        'severity' => $similarity < self::EMBEDDING_HIGH_SEVERITY_BELOW ? 'high' : 'medium',
                        'detail' => [
                            'media_ref' => $media->media_ref,
                            'spot_label' => $baseline->spot_label,
                            'embedding_similarity' => $similarity,
                            'method' => 'open_clip_vit_b_32',
                            'flag_below' => self::EMBEDDING_FLAG_BELOW,
                            'note' => 'Vision-embedding similarity below threshold — confirm visually before rejecting; not a substitute for the reviewer\'s side-by-side check.',
                        ],
                    ];
                }

                continue;
            }

            if ($media->phash === null || $baseline->baseline_phash === null) {
                continue;
            }

            $distance = $this->imageSimilarity->hashDistance($media->phash, $baseline->baseline_phash);
            if ($distance === null || $distance < self::HASH_FLAG_DISTANCE) {
                continue;
            }

            $flags[] = [
                'rule_name' => 'baseline_photo_mismatch',
                'result' => QaFlagResult::Flag,
                'severity' => $distance >= self::HASH_HIGH_SEVERITY_DISTANCE ? 'high' : 'medium',
                'detail' => [
                    'media_ref' => $media->media_ref,
                    'spot_label' => $baseline->spot_label,
                    'hash_distance' => $distance,
                    'hash_bits' => self::HASH_BITS,
                    'method' => 'perceptual_hash_fallback',
                    'note' => 'Perceptual-hash proxy only (embedding unavailable) — confirm visually before rejecting; not a substitute for the reviewer\'s side-by-side check.',
                ],
            ];
        }

        return $flags;
    }
}
