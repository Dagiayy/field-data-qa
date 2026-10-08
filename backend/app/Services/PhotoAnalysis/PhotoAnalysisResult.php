<?php

namespace App\Services\PhotoAnalysis;

/**
 * Result of one pass over a photo via the image-service's /analyze
 * endpoint: OCR text/confidence (RapidOCR), a size/framing proxy derived
 * from word bounding-box height, and the full image-quality metric set
 * (exposure, sharpness, contrast, glare, noise).
 */
class PhotoAnalysisResult
{
    /**
     * @param  list<array{text: string, confidence: float, x: int, y: int, width: int, height: int}>  $words
     */
    public function __construct(
        public readonly string $text,
        public readonly float $avgConfidence,
        public readonly ?float $textHeightRatio,
        public readonly float $brightnessMean,
        public readonly bool $isDark,
        public readonly bool $isOverexposed,
        public readonly ?int $imageWidth = null,
        public readonly ?int $imageHeight = null,
        public readonly array $words = [],
        public readonly ?float $sharpnessLaplacianVar = null,
        public readonly ?float $contrastStdDev = null,
        public readonly ?float $glareRatioPct = null,
        public readonly ?float $noiseSigma = null,
    ) {
    }
}
