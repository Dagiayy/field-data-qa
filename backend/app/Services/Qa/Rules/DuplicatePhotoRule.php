<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\ImageSimilarity\ImageSimilarityService;
use App\Services\Qa\QaRule;

/**
 * Pairwise perceptual-hash comparison across every photo in the submission.
 * A near-zero Hamming distance between two distinct media items means the
 * same image bytes were reused for two different questions/angles.
 */
class DuplicatePhotoRule implements QaRule
{
    private const DUPLICATE_HAMMING_THRESHOLD = 5;

    public function __construct(private ImageSimilarityService $imageSimilarity)
    {
    }

    public function evaluate(Submission $submission): array
    {
        $flags = [];
        $media = $submission->media->filter(fn ($m) => $m->phash !== null)->values();

        for ($i = 0; $i < $media->count(); $i++) {
            for ($j = $i + 1; $j < $media->count(); $j++) {
                $a = $media[$i];
                $b = $media[$j];
                $distance = $this->imageSimilarity->hashDistance($a->phash, $b->phash);

                if ($distance !== null && $distance <= self::DUPLICATE_HAMMING_THRESHOLD) {
                    $flags[] = [
                        'rule_name' => 'possible_reused_photo',
                        'result' => QaFlagResult::Fail,
                        'severity' => 'high',
                        'detail' => [
                            'media_refs' => [$a->media_ref, $b->media_ref],
                            'hash_distance' => $distance,
                        ],
                    ];
                }
            }
        }

        return $flags;
    }
}
