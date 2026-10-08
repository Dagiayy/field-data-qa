<?php

namespace App\Services\PhotoAnalysis;

use Illuminate\Support\Facades\Http;
use Throwable;

class HttpPhotoAnalysisService implements PhotoAnalysisService
{
    public function analyze(string $imageUrl): ?PhotoAnalysisResult
    {
        try {
            $baseUrl = rtrim((string) config('services.image_service.url'), '/');

            $response = Http::timeout(15)->post("{$baseUrl}/analyze", [
                'image_url' => $imageUrl,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            // Null (not 0) when no text was detected at all — that's a
            // legibility problem (PhotoLegibilityRule), not a framing one;
            // the size/framing ratio only means something when there's
            // some text to measure.
            $imageHeight = $data['image_height'] ?? 0;
            $tallestWordHeight = collect($data['words'] ?? [])->max('height');
            $textHeightRatio = ($imageHeight > 0 && $tallestWordHeight !== null)
                ? $tallestWordHeight / $imageHeight
                : null;

            return new PhotoAnalysisResult(
                text: $data['text'] ?? '',
                avgConfidence: (float) ($data['avg_confidence'] ?? 0),
                textHeightRatio: $textHeightRatio,
                brightnessMean: (float) ($data['brightness_mean'] ?? 0),
                isDark: (bool) ($data['is_dark'] ?? false),
                isOverexposed: (bool) ($data['is_overexposed'] ?? false),
                imageWidth: isset($data['image_width']) ? (int) $data['image_width'] : null,
                imageHeight: isset($data['image_height']) ? (int) $data['image_height'] : null,
                words: $data['words'] ?? [],
                sharpnessLaplacianVar: isset($data['sharpness_laplacian_var']) ? (float) $data['sharpness_laplacian_var'] : null,
                contrastStdDev: isset($data['contrast_std_dev']) ? (float) $data['contrast_std_dev'] : null,
                glareRatioPct: isset($data['glare_ratio_pct']) ? (float) $data['glare_ratio_pct'] : null,
                noiseSigma: isset($data['noise_sigma']) ? (float) $data['noise_sigma'] : null,
            );
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
