<?php

namespace App\Providers;

use App\Services\ImageSimilarity\HttpOpenClipEmbeddingService;
use App\Services\ImageSimilarity\ImageEmbeddingService;
use App\Services\ImageSimilarity\ImageSimilarityService;
use App\Services\ImageSimilarity\LocalPerceptualHashService;
use App\Services\PhotoAnalysis\HttpPhotoAnalysisService;
use App\Services\PhotoAnalysis\PhotoAnalysisService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Swap this binding for an HTTP-backed implementation (calling the
        // Python image-service at IMAGE_SERVICE_URL) once that service does
        // more than /health — no rule-engine code needs to change.
        $this->app->bind(ImageSimilarityService::class, LocalPerceptualHashService::class);

        // Photo QA (OCR legibility, size/framing proxy, exposure, sharpness,
        // contrast, glare, noise) always calls the image-service — there's
        // no local-only fallback for this one, since OCR/OpenCV analysis
        // isn't something GD can do.
        $this->app->bind(PhotoAnalysisService::class, HttpPhotoAnalysisService::class);

        // Vision-embedding similarity (OpenCLIP, via the image-service's
        // /embed endpoint) for baseline-vs-submitted-photo comparison —
        // separate from the local perceptual hash above, which stays wired
        // in for cheap exact-reuse detection (DuplicatePhotoRule).
        $this->app->bind(ImageEmbeddingService::class, HttpOpenClipEmbeddingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pure API backend — there is no web login page to redirect a guest
        // to (see project CLAUDE.md: Bearer-token SPA, not server-rendered).
        // Without this, Laravel's own default middleware setup redirects
        // unauthenticated non-JSON requests to a `login` named route that
        // doesn't exist, throwing RouteNotFoundException instead of a plain
        // 401. Returning null here always yields a clean 401 JSON response.
        Authenticate::redirectUsing(fn () => null);
    }
}
