<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Services\PhotoAnalysis\PhotoAnalysisResult;
use App\Services\PhotoAnalysis\PhotoAnalysisService;
use App\Services\Qa\Rules\PhotoBlurRule;
use App\Services\Qa\Rules\PhotoContrastRule;
use App\Services\Qa\Rules\PhotoExposureRule;
use App\Services\Qa\Rules\PhotoGlareRule;
use App\Services\Qa\Rules\PhotoLegibilityRule;
use App\Services\Qa\Rules\PhotoNoiseRule;
use App\Services\Qa\Rules\PhotoResolutionRule;
use App\Services\Qa\Rules\PhotoSizeFramingRule;
use App\Support\ImageServiceUrlResolver;
use Illuminate\Http\Request;

/**
 * Standalone OCR/photo-analysis test bench (frontend/src/pages/ImageTest.tsx)
 * — upload any image and see exactly what the image-service's RapidOCR +
 * OpenCV pass returns and how each photo QA rule would score it, using
 * their real thresholds. This is independent of ingestion: nothing here
 * touches a Submission/Media row, so it's safe to use for tuning/debugging
 * without polluting real data.
 */
class ImageTestController extends Controller
{
    public function analyze(Request $request, PhotoAnalysisService $photoAnalysisService)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        $path = $request->file('image')->store('test-images', 'public');
        $url = ImageServiceUrlResolver::resolve($path);

        $result = $photoAnalysisService->analyze($url);

        return response()->json([
            'success' => $result !== null,
            'result' => $result ? $this->presentResult($result) : null,
            'url' => $url,
        ]);
    }

    private function presentResult(PhotoAnalysisResult $result): array
    {
        return [
            'ocr_text' => $result->text,
            // avg_confidence is already 0-100 (same scale Media::ocr_avg_confidence
            // stores and PhotoLegibilityRule compares against) — no extra *100 here.
            'ocr_avg_confidence' => round($result->avgConfidence, 1),
            'text_height_ratio' => $result->textHeightRatio !== null ? round($result->textHeightRatio, 4) : null,
            'brightness_mean' => round($result->brightnessMean, 1),
            'is_dark' => $result->isDark,
            'is_overexposed' => $result->isOverexposed,
            'image_width' => $result->imageWidth,
            'image_height' => $result->imageHeight,
            'sharpness_laplacian_var' => $result->sharpnessLaplacianVar !== null ? round($result->sharpnessLaplacianVar, 1) : null,
            'contrast_std_dev' => $result->contrastStdDev !== null ? round($result->contrastStdDev, 1) : null,
            'glare_ratio_pct' => $result->glareRatioPct !== null ? round($result->glareRatioPct, 2) : null,
            'noise_sigma' => $result->noiseSigma !== null ? round($result->noiseSigma, 2) : null,
            'word_count' => count($result->words),
            'words' => $result->words,
            // How the real Layer 1 rules would actually score this exact
            // photo — same thresholds, so this page never drifts from
            // what ingestion would do with the same image.
            'checks' => [
                'resolution' => $this->resolutionCheck($result),
                'legibility' => $this->legibilityCheck($result),
                'framing' => $this->framingCheck($result),
                'exposure' => $this->exposureCheck($result),
                'blur' => $this->blurCheck($result),
                'contrast' => $this->contrastCheck($result),
                'glare' => $this->glareCheck($result),
                'noise' => $this->noiseCheck($result),
            ],
        ];
    }

    private function resolutionCheck(PhotoAnalysisResult $result): array
    {
        $tooSmall = PhotoResolutionRule::isTooSmall($result->imageWidth, $result->imageHeight);

        return [
            'result' => $tooSmall === null ? 'not_applicable' : ($tooSmall ? 'fail' : 'pass'),
            'min_width' => PhotoResolutionRule::MIN_WIDTH,
            'min_height' => PhotoResolutionRule::MIN_HEIGHT,
            'recommended_width' => PhotoResolutionRule::RECOMMENDED_WIDTH,
            'recommended_height' => PhotoResolutionRule::RECOMMENDED_HEIGHT,
        ];
    }

    private function legibilityCheck(PhotoAnalysisResult $result): array
    {
        $noText = trim($result->text) === '';

        return [
            'result' => match (true) {
                $noText || $result->avgConfidence < PhotoLegibilityRule::MIN_ACCEPTABLE_CONFIDENCE => 'fail',
                $result->avgConfidence < PhotoLegibilityRule::GOOD_CONFIDENCE => 'flag',
                default => 'pass',
            },
            'min_acceptable_confidence' => PhotoLegibilityRule::MIN_ACCEPTABLE_CONFIDENCE,
            'good_confidence' => PhotoLegibilityRule::GOOD_CONFIDENCE,
        ];
    }

    private function framingCheck(PhotoAnalysisResult $result): array
    {
        $ratio = $result->textHeightRatio;

        return [
            'result' => match (true) {
                $ratio === null => 'not_applicable',
                $ratio < PhotoSizeFramingRule::HARD_FAIL_RATIO => 'fail',
                $ratio < PhotoSizeFramingRule::BORDERLINE_RATIO => 'flag',
                default => 'pass',
            },
            'hard_fail_ratio' => PhotoSizeFramingRule::HARD_FAIL_RATIO,
            'borderline_ratio' => PhotoSizeFramingRule::BORDERLINE_RATIO,
        ];
    }

    private function exposureCheck(PhotoAnalysisResult $result): array
    {
        return [
            'result' => ($result->isDark || $result->isOverexposed) ? 'flag' : 'pass',
            'dark_threshold' => PhotoExposureRule::DARK_THRESHOLD,
            'overexposed_threshold' => PhotoExposureRule::OVEREXPOSED_THRESHOLD,
        ];
    }

    private function blurCheck(PhotoAnalysisResult $result): array
    {
        $variance = $result->sharpnessLaplacianVar;

        return [
            'result' => match (true) {
                $variance === null => 'not_applicable',
                $variance < PhotoBlurRule::HARD_FAIL_VARIANCE => 'fail',
                $variance < PhotoBlurRule::BORDERLINE_VARIANCE => 'flag',
                default => 'pass',
            },
            'hard_fail_variance' => PhotoBlurRule::HARD_FAIL_VARIANCE,
            'borderline_variance' => PhotoBlurRule::BORDERLINE_VARIANCE,
        ];
    }

    private function contrastCheck(PhotoAnalysisResult $result): array
    {
        $stdDev = $result->contrastStdDev;

        return [
            'result' => $stdDev === null ? 'not_applicable' : ($stdDev < PhotoContrastRule::POOR_THRESHOLD ? 'flag' : 'pass'),
            'poor_threshold' => PhotoContrastRule::POOR_THRESHOLD,
        ];
    }

    private function glareCheck(PhotoAnalysisResult $result): array
    {
        $ratio = $result->glareRatioPct;

        return [
            'result' => match (true) {
                $ratio === null => 'not_applicable',
                $ratio > PhotoGlareRule::REJECT_RATIO_PCT => 'fail',
                $ratio > PhotoGlareRule::WARNING_RATIO_PCT => 'flag',
                default => 'pass',
            },
            'warning_threshold_pct' => PhotoGlareRule::WARNING_RATIO_PCT,
            'reject_threshold_pct' => PhotoGlareRule::REJECT_RATIO_PCT,
        ];
    }

    private function noiseCheck(PhotoAnalysisResult $result): array
    {
        $sigma = $result->noiseSigma;

        return [
            'result' => match (true) {
                $sigma === null => 'not_applicable',
                $sigma > PhotoNoiseRule::REJECT_SIGMA => 'fail',
                $sigma > PhotoNoiseRule::WARNING_SIGMA => 'flag',
                default => 'pass',
            },
            'warning_threshold' => PhotoNoiseRule::WARNING_SIGMA,
            'reject_threshold' => PhotoNoiseRule::REJECT_SIGMA,
        ];
    }
}
