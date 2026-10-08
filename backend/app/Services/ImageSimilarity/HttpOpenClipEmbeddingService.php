<?php

namespace App\Services\ImageSimilarity;

use Illuminate\Support\Facades\Http;
use Throwable;

class HttpOpenClipEmbeddingService implements ImageEmbeddingService
{
    public function embed(string $fileRefOrUrl): ?array
    {
        try {
            $baseUrl = rtrim((string) config('services.image_service.url'), '/');

            $response = Http::timeout(30)->post("{$baseUrl}/embed", [
                'image_url' => $fileRefOrUrl,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $embedding = $response->json('embedding');

            return is_array($embedding) && ! empty($embedding) ? array_map('floatval', $embedding) : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function cosineSimilarity(?array $a, ?array $b): ?float
    {
        if ($a === null || $b === null || empty($a) || empty($b) || count($a) !== count($b)) {
            return null;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $valueA) {
            $valueB = $b[$i];
            $dot += $valueA * $valueB;
            $normA += $valueA * $valueA;
            $normB += $valueB * $valueB;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return null;
        }

        $similarity = $dot / (sqrt($normA) * sqrt($normB));

        // Clamp for float rounding drift beyond the mathematically valid
        // [-1, 1] range.
        return round(max(-1.0, min(1.0, $similarity)), 4);
    }
}
