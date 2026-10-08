<?php

namespace App\Services\Qa;

use App\Models\QaFlag;
use App\Models\Submission;
use App\Services\ImageSimilarity\ImageEmbeddingService;
use App\Services\ImageSimilarity\ImageSimilarityService;
use App\Services\PhotoAnalysis\PhotoAnalysisService;
use App\Services\Pricing\MarketPriceRangeResolver;
use App\Services\Qa\Rules\AnswerPatternRule;
use App\Services\Qa\Rules\BaselineLocationRule;
use App\Services\Qa\Rules\BaselineVisualSimilarityRule;
use App\Services\Qa\Rules\DuplicateLocationRule;
use App\Services\Qa\Rules\DuplicatePhotoRule;
use App\Services\Qa\Rules\GpsAccuracyRule;
use App\Services\Qa\Rules\OutletGeofenceRule;
use App\Services\Qa\Rules\PhotoBlurRule;
use App\Services\Qa\Rules\PhotoContrastRule;
use App\Services\Qa\Rules\PhotoExposureRule;
use App\Services\Qa\Rules\PhotoGlareRule;
use App\Services\Qa\Rules\PhotoLegibilityRule;
use App\Services\Qa\Rules\PhotoNoiseRule;
use App\Services\Qa\Rules\PhotoResolutionRule;
use App\Services\Qa\Rules\PhotoSizeFramingRule;
use App\Services\Qa\Rules\PriceRangeRule;
use App\Services\Qa\Rules\RequiredFieldsRule;
use App\Services\Qa\Rules\SurveyDurationRule;
use App\Services\Qa\Rules\TimeSequenceRule;
use App\Services\Qa\Rules\TimestampConsistencyRule;
use App\Support\ImageServiceUrlResolver;
use Illuminate\Support\Collection;

/**
 * Layer 1 automated QA. Runs the full rule set against a freshly-ingested
 * submission and persists one QaFlag row per violation found — a clean
 * submission produces zero rows, it never writes "pass" rows for checks
 * that didn't trip. This never changes submission status itself; it only
 * surfaces flags for the Layer 2 human reviewer.
 */
class QaRuleEngine
{
    public function __construct(
        private ImageSimilarityService $imageSimilarity,
        private ImageEmbeddingService $imageEmbedding,
        private PhotoAnalysisService $photoAnalysis,
        private MarketPriceRangeResolver $marketPriceRangeResolver,
        private AgentAnswerPatternAnalyzer $answerPatternAnalyzer,
    ) {
    }

    /**
     * @return Collection<int, QaFlag>
     */
    public function run(Submission $submission): Collection
    {
        $submission->loadMissing(['quest', 'outlet.baselines', 'answers', 'media', 'prices']);

        $this->computeMissingPhashes($submission);
        $this->computeMissingPhotoAnalysis($submission);
        $this->computeMissingEmbeddings($submission);

        $rules = [
            new OutletGeofenceRule,
            new BaselineLocationRule($this->imageSimilarity, $this->imageEmbedding),
            new BaselineVisualSimilarityRule($this->imageSimilarity, $this->imageEmbedding),
            new GpsAccuracyRule,
            new DuplicateLocationRule,
            new TimestampConsistencyRule,
            new TimeSequenceRule,
            new SurveyDurationRule,
            new PriceRangeRule($this->marketPriceRangeResolver),
            new DuplicatePhotoRule($this->imageSimilarity),
            new RequiredFieldsRule,
            new AnswerPatternRule($this->answerPatternAnalyzer),
            new PhotoResolutionRule,
            new PhotoLegibilityRule,
            new PhotoExposureRule,
            new PhotoSizeFramingRule,
            new PhotoBlurRule,
            new PhotoContrastRule,
            new PhotoGlareRule,
            new PhotoNoiseRule,
        ];

        $flagRows = collect();

        foreach ($rules as $rule) {
            foreach ($rule->evaluate($submission) as $flagData) {
                $flagRows->push(QaFlag::create([
                    'submission_id' => $submission->id,
                    'rule_name' => $flagData['rule_name'],
                    'result' => $flagData['result'],
                    'severity' => $flagData['severity'],
                    'detail' => $flagData['detail'],
                ]));
            }
        }

        return $flagRows;
    }

    private function computeMissingPhashes(Submission $submission): void
    {
        foreach ($submission->media as $media) {
            if ($media->phash !== null) {
                continue;
            }

            $hash = $this->imageSimilarity->hash($media->file_path);
            if ($hash !== null) {
                $media->phash = $hash;
                $media->save();
            }
        }
    }

    private function computeMissingPhotoAnalysis(Submission $submission): void
    {
        foreach ($submission->media as $media) {
            if ($media->ocr_avg_confidence !== null) {
                continue;
            }

            $url = ImageServiceUrlResolver::resolve($media->file_path);
            if ($url === null) {
                continue;
            }

            $result = $this->photoAnalysis->analyze($url);
            if ($result === null) {
                continue;
            }

            $media->ocr_text = $result->text;
            $media->ocr_avg_confidence = $result->avgConfidence;
            $media->text_height_ratio = $result->textHeightRatio;
            $media->brightness_mean = $result->brightnessMean;
            $media->image_width = $result->imageWidth;
            $media->image_height = $result->imageHeight;
            $media->sharpness_laplacian_var = $result->sharpnessLaplacianVar;
            $media->contrast_std_dev = $result->contrastStdDev;
            $media->glare_ratio_pct = $result->glareRatioPct;
            $media->noise_sigma = $result->noiseSigma;
            $media->save();
        }
    }

    private function computeMissingEmbeddings(Submission $submission): void
    {
        foreach ($submission->media as $media) {
            if ($media->embedding !== null) {
                continue;
            }

            $url = ImageServiceUrlResolver::resolve($media->file_path);
            if ($url === null) {
                continue;
            }

            $embedding = $this->imageEmbedding->embed($url);
            if ($embedding !== null) {
                $media->embedding = $embedding;
                $media->save();
            }
        }
    }
}
