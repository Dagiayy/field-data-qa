<?php

namespace App\Services\PhotoAnalysis;

/**
 * Abstraction over "what does OCR/exposure analysis say about this photo" —
 * a rule never talks to the image-service HTTP API directly. Returns null
 * if the photo couldn't be fetched/analyzed (e.g. image-service down), in
 * which case the calling rule should simply skip rather than fail the
 * whole ingestion.
 */
interface PhotoAnalysisService
{
    public function analyze(string $imageUrl): ?PhotoAnalysisResult;
}
