<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Media;
use App\Models\OutletBaseline;
use App\Models\Submission;
use App\Services\Geo\Haversine;
use App\Services\ImageSimilarity\ImageEmbeddingService;
use App\Services\ImageSimilarity\ImageSimilarityService;
use App\Services\Qa\QaRule;
use App\Support\ImageServiceUrlResolver;
use Illuminate\Support\Collection;

/**
 * Compares each photo's gps_at_capture against the OutletBaseline spot it
 * belongs to. The envelope doesn't carry spot_label directly, so the match
 * is resolved by (a) the single baseline an outlet has, if there's only
 * one, (b) a keyword match between the baseline's spot_label and the
 * photo's question_id, or (c) failing that, whichever baseline is
 * geographically nearest.
 *
 * An outlet with NO registered baseline at all means this check (and
 * BaselineVisualSimilarityRule's photo comparison, which depends on the
 * same match) never actually ran for any photo in the submission — that's
 * a Baseline Management setup gap, not photos that happened to check out.
 * Flagged explicitly (mirroring SurveyDurationRule's
 * no_duration_baseline_configured) so GPS/Photo Trust Score dimensions
 * don't silently default to 100 just because nothing existed to compare
 * against yet.
 */
class BaselineLocationRule implements QaRule
{
    public function __construct(
        private ImageSimilarityService $imageSimilarity,
        private ImageEmbeddingService $imageEmbedding,
    ) {
    }

    public function evaluate(Submission $submission): array
    {
        $flags = [];
        $baselines = $submission->outlet?->baselines ?? collect();

        if ($baselines->isEmpty()) {
            if ($submission->media->isNotEmpty()) {
                return [[
                    'rule_name' => 'no_outlet_baseline_configured',
                    'result' => QaFlagResult::Flag,
                    'severity' => 'low',
                    'detail' => [
                        'outlet_id' => $submission->outlet?->code,
                    ],
                ]];
            }

            return $flags;
        }

        foreach ($submission->media as $media) {
            if ($media->gps_at_capture_lat === null || $media->gps_at_capture_lng === null) {
                continue;
            }

            $baseline = $this->resolveBaseline($baselines, $media);
            if (! $baseline) {
                continue;
            }

            if ($media->matched_baseline_id !== $baseline->id) {
                $media->matched_baseline_id = $baseline->id;
                $media->save();
            }

            if ($baseline->baseline_phash === null) {
                $baselineHash = $this->imageSimilarity->hash($baseline->baseline_photo_path);
                if ($baselineHash !== null) {
                    $baseline->baseline_phash = $baselineHash;
                    $baseline->save();
                }
            }

            if ($baseline->baseline_embedding === null) {
                $baselineUrl = ImageServiceUrlResolver::resolve($baseline->baseline_photo_path);
                $baselineEmbedding = $baselineUrl !== null ? $this->imageEmbedding->embed($baselineUrl) : null;
                if ($baselineEmbedding !== null) {
                    $baseline->baseline_embedding = $baselineEmbedding;
                    $baseline->save();
                }
            }

            $distance = Haversine::distanceMeters(
                (float) $media->gps_at_capture_lat,
                (float) $media->gps_at_capture_lng,
                (float) $baseline->baseline_gps_lat,
                (float) $baseline->baseline_gps_lng,
            );

            if ($distance > $baseline->baseline_gps_radius_m) {
                $flags[] = [
                    'rule_name' => 'possible_wrong_location',
                    'result' => QaFlagResult::Fail,
                    'severity' => 'high',
                    'detail' => [
                        'media_ref' => $media->media_ref,
                        'spot_label' => $baseline->spot_label,
                        'distance_m' => round($distance, 1),
                        'allowed_radius_m' => $baseline->baseline_gps_radius_m,
                    ],
                ];
            }
        }

        return $flags;
    }

    /**
     * @param  Collection<int, OutletBaseline>  $baselines
     */
    private function resolveBaseline(Collection $baselines, Media $media): ?OutletBaseline
    {
        if ($baselines->count() === 1) {
            return $baselines->first();
        }

        $questionId = strtolower($media->question_id);

        foreach ($baselines as $baseline) {
            $tokens = array_filter(
                explode('_', strtolower($baseline->spot_label)),
                fn ($token) => strlen($token) >= 3,
            );

            foreach ($tokens as $token) {
                if (str_contains($questionId, $token)) {
                    return $baseline;
                }
            }
        }

        return $baselines->sortBy(fn (OutletBaseline $baseline) => Haversine::distanceMeters(
            (float) $media->gps_at_capture_lat,
            (float) $media->gps_at_capture_lng,
            (float) $baseline->baseline_gps_lat,
            (float) $baseline->baseline_gps_lng,
        ))->first();
    }
}
