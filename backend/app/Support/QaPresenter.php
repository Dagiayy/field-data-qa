<?php

namespace App\Support;

use App\Models\Answer;
use App\Models\Media;
use App\Models\Outlet;
use App\Models\Price;
use App\Models\QaFlag;
use App\Models\QaReview;
use App\Models\Quest;
use App\Models\Submission;
use App\Models\SubmissionTrustScore;
use App\Services\Geo\Haversine;
use App\Services\ImageSimilarity\ImageEmbeddingService;
use App\Services\ImageSimilarity\ImageSimilarityService;
use App\Services\Pricing\MarketPriceRangeResolver;
use App\Services\Pricing\SubmissionAnswerLookup;
use App\Services\Qa\Rules\GpsAccuracyRule;
use App\Services\Qa\Rules\PhotoBlurRule;
use App\Services\Qa\Rules\PhotoContrastRule;
use App\Services\Qa\Rules\PhotoExposureRule;
use App\Services\Qa\Rules\PhotoGlareRule;
use App\Services\Qa\Rules\PhotoLegibilityRule;
use App\Services\Qa\Rules\PhotoNoiseRule;
use App\Services\Qa\Rules\PhotoResolutionRule;
use App\Services\Qa\Rules\PhotoSizeFramingRule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Single place that shapes Eloquent models into the exact JSON the QA
 * dashboard's TypeScript types (frontend/src/types/index.ts) expect —
 * including translating internal business keys (quest.form_code,
 * outlet.code) back into the "id" fields the frontend filters on, and
 * mapping backend enums onto the frontend's simplified status vocabulary.
 */
class QaPresenter
{
    public function __construct(
        private ImageSimilarityService $imageSimilarity,
        private ImageEmbeddingService $imageEmbedding,
        private MarketPriceRangeResolver $marketPriceRangeResolver,
    ) {
    }

    public function queueItem(Submission $submission, ?float $agentOverallTrustScore = null): array
    {
        return [
            'id' => $submission->id,
            'quest' => $this->questSummary($submission->quest),
            'outlet' => $this->outletSummary($submission->outlet, $submission->quest),
            'agent_id' => $submission->agent_id,
            'submitted_at' => optional($submission->submitted_at)->toIso8601String(),
            'status' => $submission->status->toApiValue(),
            'flags' => $submission->qaFlags->map(fn (QaFlag $flag) => $this->flag($flag))->all(),
            'trust_score' => $this->trustScoreItem($submission->trustScore),
            // The Trust Score *column* in the QA Queue is the agent's
            // overall standing (average across all their scored
            // submissions), not this one submission's own score — a
            // reviewer scanning the queue is judging the agent, not
            // re-litigating this single row.
            'agent_overall_trust_score' => $agentOverallTrustScore !== null ? round($agentOverallTrustScore, 2) : null,
            // The Backcheck queue's "Rejected" tab needs the actual
            // rejection reason right in the list — not just the automated
            // QaFlag rows, which say what the *engine* caught, not what the
            // *reviewer* decided.
            'latest_review' => ($latestReview = $this->latestReview($submission)) ? $this->reviewItem($latestReview) : null,
            'payment_status' => $submission->paymentStatus?->wallet_state->toApiValue(),
            'payout_amount' => $submission->paymentStatus?->amount !== null
                ? (float) $submission->paymentStatus->amount
                : null,
        ];
    }

    public function submissionDetail(Submission $submission, ?float $agentOverallTrustScore = null): array
    {
        return [
            'id' => $submission->id,
            'submission_id' => $submission->id,
            'quest_id' => $submission->quest?->form_code,
            'quest' => $this->questSummary($submission->quest),
            'outlet_id' => $submission->outlet?->code,
            'outlet' => $this->outletSummary($submission->outlet, $submission->quest),
            'agent_id' => $submission->agent_id,
            'config_version' => $submission->config_version,
            'survey_start_at' => optional($submission->survey_start_at)->toIso8601String(),
            'survey_end_at' => optional($submission->survey_end_at)->toIso8601String(),
            'submitted_at' => optional($submission->submitted_at)->toIso8601String(),
            'timestamps' => [
                'quest_accepted_at' => optional($submission->quest_accepted_at)->toIso8601String(),
                'survey_start_at' => optional($submission->survey_start_at)->toIso8601String(),
                'survey_end_at' => optional($submission->survey_end_at)->toIso8601String(),
                'submitted_at' => optional($submission->submitted_at)->toIso8601String(),
                'accept_to_start_seconds' => ($submission->quest_accepted_at && $submission->survey_start_at)
                    ? $submission->quest_accepted_at->diffInSeconds($submission->survey_start_at)
                    : null,
            ],
            // Quest-level Baseline Management (SurveyDurationRule) — lets
            // the dashboard show the real configured expected range instead
            // of a hardcoded "> 2 mins" guess, and distinguish "no baseline
            // set up for this quest yet" from "duration checked and fine".
            'quest_baseline' => $submission->quest ? [
                'has_duration_baseline' => $submission->quest->hasDurationBaseline(),
                'expected_duration_min_seconds' => $submission->quest->expected_duration_min_seconds,
                'expected_duration_max_seconds' => $submission->quest->expected_duration_max_seconds,
            ] : null,
            'gps' => [
                'lat' => (float) $submission->gps_lat,
                'lng' => (float) $submission->gps_lng,
                'accuracy_m' => $submission->gps_accuracy_m !== null ? (float) $submission->gps_accuracy_m : null,
                // Piped through structurally (like PhotoResolutionRule's
                // min/recommended constants) so the dashboard shows the
                // real enforced threshold rather than a frontend constant
                // that can silently drift out of sync with the rule.
                'max_acceptable_accuracy_m' => GpsAccuracyRule::MAX_ACCEPTABLE_ACCURACY_M,
            ],
            'status' => $submission->status->toApiValue(),
            'answers' => $submission->answers->map(fn (Answer $answer) => [
                'question_id' => $answer->question_id,
                'input_type' => $answer->input_type,
                'value' => $answer->value,
                'row_id' => $answer->row_id,
                'media_ref' => $answer->media_ref,
            ])->all(),
            'media' => $submission->media->map(fn (Media $media) => $this->mediaItem($media))->all(),
            'prices' => $submission->prices->map(fn (Price $price) => $this->priceItem($price, $submission))->all(),
            'flags' => $submission->qaFlags->map(fn (QaFlag $flag) => $this->flag($flag))->all(),
            'review_history' => $submission->qaReviews
                ->sortBy('reviewed_at')
                ->values()
                ->map(fn (QaReview $review) => $this->reviewItem($review))
                ->all(),
            'client_app_version' => $submission->client_app_version,
            'trust_score' => $this->trustScoreItem($submission->trustScore),
            'agent_overall_trust_score' => $agentOverallTrustScore !== null ? round($agentOverallTrustScore, 2) : null,
            'payment_status' => $submission->paymentStatus?->wallet_state->toApiValue(),
            'payout_amount' => $submission->paymentStatus?->amount !== null
                ? (float) $submission->paymentStatus->amount
                : null,
        ];
    }

    public function outletSummary(?Outlet $outlet, ?Quest $quest = null): ?array
    {
        if (! $outlet) {
            return null;
        }

        return [
            'id' => $outlet->code,
            'name' => $outlet->name,
            'branch' => $outlet->branch,
            'city' => $outlet->city,
            'gps_lat' => $outlet->gps_lat !== null ? (float) $outlet->gps_lat : null,
            'gps_lng' => $outlet->gps_lng !== null ? (float) $outlet->gps_lng : null,
            'approved_radius_m' => $outlet->gps_radius_m,
            'geofence_radius_m' => $quest?->geofence_radius_m,
        ];
    }

    public function questSummary(?Quest $quest): ?array
    {
        if (! $quest) {
            return null;
        }

        return [
            'id' => $quest->form_code,
            'title' => $quest->title,
            'form_code' => $quest->form_code,
            'quest_type' => $quest->quest_type->value,
        ];
    }

    public function flag(QaFlag $flag): array
    {
        $detail = $flag->detail ?? [];

        // Normalizes both single-photo rules ('media_ref') and pair rules
        // like the duplicate-photo hash match ('media_refs', plural) into
        // one list, so the frontend can reliably show "which flags apply to
        // this specific photo" without string-parsing the detail text.
        $mediaRefs = [];
        if (isset($detail['media_ref'])) {
            $mediaRefs[] = $detail['media_ref'];
        }
        if (isset($detail['media_refs']) && is_array($detail['media_refs'])) {
            $mediaRefs = array_merge($mediaRefs, $detail['media_refs']);
        }
        if (isset($detail['matched_media_ref'])) {
            $mediaRefs[] = $detail['matched_media_ref'];
        }

        return [
            'rule_name' => $flag->rule_name,
            'result' => $flag->result->value,
            'severity' => $flag->severity,
            'detail' => $this->formatDetail($detail),
            'media_refs' => ! empty($mediaRefs) ? array_values(array_unique($mediaRefs)) : null,
            'sku_id' => $detail['sku_id'] ?? null,
            // Single-question rules (answer_pattern_repetitive) carry this
            // in their detail array — exposed as its own field, same as
            // sku_id above, so the frontend can match a flag to the exact
            // answer it's about without string-parsing the formatted detail.
            'question_id' => $detail['question_id'] ?? null,
        ];
    }

    private function trustScoreItem(?SubmissionTrustScore $score): ?array
    {
        if (! $score) {
            return null;
        }

        return [
            'total_score' => (float) $score->total_score,
            'gps_score' => (float) $score->gps_score,
            'time_score' => (float) $score->time_score,
            'photo_score' => (float) $score->photo_score,
            'completeness_score' => (float) $score->completeness_score,
            'audit_confirmation_score' => (float) $score->audit_confirmation_score,
            'weights_used' => $score->weights_used,
        ];
    }

    private function mediaItem(Media $media): array
    {
        $baseline = $media->matchedBaseline;
        $baselineData = null;

        if ($baseline) {
            $gpsDistance = ($media->gps_at_capture_lat !== null && $media->gps_at_capture_lng !== null)
                ? Haversine::distanceMeters(
                    (float) $media->gps_at_capture_lat,
                    (float) $media->gps_at_capture_lng,
                    (float) $baseline->baseline_gps_lat,
                    (float) $baseline->baseline_gps_lng,
                )
                : null;

            // Vision-embedding cosine similarity (OpenCLIP) is the real
            // signal; the perceptual hash distance is kept alongside it
            // purely as a cheap secondary/debug reference, falling back to
            // hash-derived similarity only if no embedding exists yet for
            // either photo (e.g. image-service was briefly unreachable).
            $embeddingSimilarity = $this->imageEmbedding->cosineSimilarity($media->embedding, $baseline->baseline_embedding);
            $similarityMethod = 'open_clip_vit_b_32';
            if ($embeddingSimilarity === null) {
                $embeddingSimilarity = $this->imageSimilarity->embeddingSimilarity($media->phash, $baseline->baseline_phash);
                $similarityMethod = 'perceptual_hash_fallback';
            }

            $baselineData = [
                'photo_url' => $this->resolveUrl($baseline->baseline_photo_path),
                'spot_label' => $baseline->spot_label,
                'hash_distance' => $this->imageSimilarity->hashDistance($media->phash, $baseline->baseline_phash),
                'embedding_similarity' => $embeddingSimilarity,
                'similarity_method' => $similarityMethod,
                'gps_distance_m' => $gpsDistance !== null ? round($gpsDistance, 1) : null,
            ];
        }

        return [
            'media_ref' => $media->media_ref,
            'question_id' => $media->question_id,
            'row_id' => $media->row_id,
            'media_type' => $media->media_type,
            'url' => $this->resolveUrl($media->file_path),
            'file_ref' => $media->file_path,
            'captured_at' => optional($media->captured_at)->toIso8601String(),
            'gps_at_capture' => [
                'lat' => $media->gps_at_capture_lat !== null ? (float) $media->gps_at_capture_lat : null,
                'lng' => $media->gps_at_capture_lng !== null ? (float) $media->gps_at_capture_lng : null,
            ],
            'baseline' => $baselineData,
            'photo_analysis' => $this->photoAnalysisSummary($media),
        ];
    }

    private function priceItem(Price $price, Submission $submission): array
    {
        $range = $this->marketPriceRangeResolver->resolve($price, $submission->quest_id);
        $value = (float) $price->value;

        $direction = null;
        if ($range !== null) {
            $direction = $value > $range['max'] ? 'above' : ($value < $range['min'] ? 'below' : 'within');
        }

        return [
            'question_id' => $price->question_id,
            'sku_id' => $price->sku_id,
            'row_id' => $price->row_id,
            'value' => $value,
            'currency' => $price->currency,
            'unit' => $price->unit,
            // Cross-referenced from answers[] rather than duplicated per
            // price at ingestion — these apply to the whole submission, not
            // per SKU, per the docs/ sample payloads (q5b_price_type,
            // q5c_trade_type, q5d_discounted carry no row_id).
            'package_size' => SubmissionAnswerLookup::packSize($submission, $price->row_id),
            'trade_type' => SubmissionAnswerLookup::byKeyword($submission, 'trade_type'),
            'price_type' => SubmissionAnswerLookup::byKeyword($submission, 'price_type'),
            'discounted' => SubmissionAnswerLookup::byKeyword($submission, 'discount'),
            'market_range' => $range !== null ? [
                'min' => $range['min'],
                'max' => $range['max'],
                'source' => $range['source'],
                'direction' => $direction,
            ] : null,
        ];
    }

    /**
     * Every "Photos Evidence & Baseline Verification" quality dimension the
     * QA dashboard displays, each as {value, verdict} where verdict is the
     * same pass/flag/fail vocabulary as QAFlag.result — computed here from
     * the exact same constants the Layer 1 rules use, so the card can never
     * show a status that disagrees with the flags actually raised.
     * Product visibility/occlusion/multi-product/angle/name-matching are
     * deliberately not included: they need an object-detection model and a
     * product catalog that don't exist in this system yet.
     */
    private function photoAnalysisSummary(Media $media): ?array
    {
        if ($media->ocr_avg_confidence === null && $media->brightness_mean === null) {
            return null;
        }

        return [
            'ocr_text' => $media->ocr_text,
            'ocr_avg_confidence' => $media->ocr_avg_confidence !== null ? round((float) $media->ocr_avg_confidence, 1) : null,
            'text_height_ratio' => $media->text_height_ratio !== null ? round((float) $media->text_height_ratio, 4) : null,
            'brightness_mean' => $media->brightness_mean !== null ? round((float) $media->brightness_mean, 1) : null,
            'is_dark' => $media->brightness_mean !== null ? (float) $media->brightness_mean < PhotoExposureRule::DARK_THRESHOLD : null,
            'is_overexposed' => $media->brightness_mean !== null ? (float) $media->brightness_mean > PhotoExposureRule::OVEREXPOSED_THRESHOLD : null,

            'resolution' => [
                'width' => $media->image_width,
                'height' => $media->image_height,
                'verdict' => $this->resolutionVerdict($media),
                'min_width' => PhotoResolutionRule::MIN_WIDTH,
                'min_height' => PhotoResolutionRule::MIN_HEIGHT,
                'recommended_width' => PhotoResolutionRule::RECOMMENDED_WIDTH,
                'recommended_height' => PhotoResolutionRule::RECOMMENDED_HEIGHT,
            ],
            'blur' => [
                'laplacian_variance' => $media->sharpness_laplacian_var !== null ? round((float) $media->sharpness_laplacian_var, 1) : null,
                'verdict' => $this->tieredVerdict($media->sharpness_laplacian_var, PhotoBlurRule::HARD_FAIL_VARIANCE, PhotoBlurRule::BORDERLINE_VARIANCE),
                'hard_fail_variance' => PhotoBlurRule::HARD_FAIL_VARIANCE,
                'borderline_variance' => PhotoBlurRule::BORDERLINE_VARIANCE,
            ],
            'contrast' => [
                'std_dev' => $media->contrast_std_dev !== null ? round((float) $media->contrast_std_dev, 1) : null,
                'verdict' => $media->contrast_std_dev === null
                    ? null
                    : ((float) $media->contrast_std_dev < PhotoContrastRule::POOR_THRESHOLD ? 'flag' : 'pass'),
                'poor_threshold' => PhotoContrastRule::POOR_THRESHOLD,
            ],
            'glare' => [
                'ratio_pct' => $media->glare_ratio_pct !== null ? round((float) $media->glare_ratio_pct, 2) : null,
                'verdict' => $this->inverseTieredVerdict($media->glare_ratio_pct, PhotoGlareRule::WARNING_RATIO_PCT, PhotoGlareRule::REJECT_RATIO_PCT),
                'warning_threshold_pct' => PhotoGlareRule::WARNING_RATIO_PCT,
                'reject_threshold_pct' => PhotoGlareRule::REJECT_RATIO_PCT,
            ],
            'noise' => [
                'sigma' => $media->noise_sigma !== null ? round((float) $media->noise_sigma, 2) : null,
                'verdict' => $this->inverseTieredVerdict($media->noise_sigma, PhotoNoiseRule::WARNING_SIGMA, PhotoNoiseRule::REJECT_SIGMA),
                'warning_threshold' => PhotoNoiseRule::WARNING_SIGMA,
                'reject_threshold' => PhotoNoiseRule::REJECT_SIGMA,
            ],
            'legibility' => [
                'verdict' => $this->legibilityVerdict($media),
                'min_acceptable_confidence' => PhotoLegibilityRule::MIN_ACCEPTABLE_CONFIDENCE,
                'good_confidence' => PhotoLegibilityRule::GOOD_CONFIDENCE,
            ],
            'size_framing' => [
                'verdict' => $this->tieredVerdict($media->text_height_ratio, PhotoSizeFramingRule::HARD_FAIL_RATIO, PhotoSizeFramingRule::BORDERLINE_RATIO),
                'hard_fail_ratio' => PhotoSizeFramingRule::HARD_FAIL_RATIO,
                'borderline_ratio' => PhotoSizeFramingRule::BORDERLINE_RATIO,
            ],
        ];
    }

    private function resolutionVerdict(Media $media): ?string
    {
        $tooSmall = PhotoResolutionRule::isTooSmall(
            $media->image_width !== null ? (int) $media->image_width : null,
            $media->image_height !== null ? (int) $media->image_height : null,
        );

        if ($tooSmall === null) {
            return null;
        }

        return $tooSmall ? 'fail' : 'pass';
    }

    private function legibilityVerdict(Media $media): ?string
    {
        if ($media->ocr_avg_confidence === null) {
            return null;
        }

        $noText = trim((string) $media->ocr_text) === '';
        $confidence = (float) $media->ocr_avg_confidence;

        if ($noText || $confidence < PhotoLegibilityRule::MIN_ACCEPTABLE_CONFIDENCE) {
            return 'fail';
        }

        return $confidence < PhotoLegibilityRule::GOOD_CONFIDENCE ? 'flag' : 'pass';
    }

    /**
     * "Higher is better" tiering: below $failBelow is a fail, below
     * $flagBelow is a flag, otherwise pass.
     */
    private function tieredVerdict(?float $value, float $failBelow, float $flagBelow): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value < $failBelow) {
            return 'fail';
        }

        return $value < $flagBelow ? 'flag' : 'pass';
    }

    /**
     * "Lower is better" tiering: above $failAbove is a fail, above
     * $flagAbove is a flag, otherwise pass.
     */
    private function inverseTieredVerdict(?float $value, float $flagAbove, float $failAbove): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value > $failAbove) {
            return 'fail';
        }

        return $value > $flagAbove ? 'flag' : 'pass';
    }

    private function latestReview(Submission $submission): ?QaReview
    {
        return $submission->qaReviews->sortByDesc('reviewed_at')->first();
    }

    private function reviewItem(QaReview $review): array
    {
        return [
            'id' => (string) $review->id,
            'reviewer_name' => $review->reviewer?->name,
            'decision' => $review->decision->value,
            'reason_code' => $review->rejectionReason?->code,
            'reason_label' => $review->rejectionReason?->label,
            'note' => $review->note,
            'reviewed_at' => optional($review->reviewed_at)->toIso8601String(),
        ];
    }

    private function resolveUrl(?string $ref): ?string
    {
        if ($ref === null) {
            return null;
        }

        if (Str::startsWith($ref, ['http://', 'https://'])) {
            return $ref;
        }

        return Storage::disk('public')->url($ref);
    }

    private function formatDetail(?array $detail): ?string
    {
        if (empty($detail)) {
            return null;
        }

        $parts = [];
        foreach ($detail as $key => $value) {
            $parts[] = Str::headline((string) $key).': '.(is_array($value) ? json_encode($value) : $value);
        }

        return implode(' — ', $parts);
    }
}
