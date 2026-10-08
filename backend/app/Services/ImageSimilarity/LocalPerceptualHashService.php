<?php

namespace App\Services\ImageSimilarity;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Default local implementation: an 8x8 average-hash (aHash) computed with
 * GD, no external dependency. Good enough to catch exact/near-exact photo
 * reuse (the case the automated rule engine needs today). Real
 * location/angle embedding similarity is a job for the Python image-service
 * (open_clip) — see ImageSimilarityService for the swap point.
 */
class LocalPerceptualHashService implements ImageSimilarityService
{
    private const HASH_SIZE = 8;

    public function hash(string $fileRefOrUrl): ?string
    {
        $bytes = $this->fetchBytes($fileRefOrUrl);
        if ($bytes === null) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }

        $size = self::HASH_SIZE;
        $resized = imagecreatetruecolor($size, $size);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));
        imagefilter($resized, IMG_FILTER_GRAYSCALE);

        $values = [];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $values[] = imagecolorat($resized, $x, $y) & 0xFF;
            }
        }

        imagedestroy($image);
        imagedestroy($resized);

        $mean = array_sum($values) / count($values);

        $bits = '';
        foreach ($values as $value) {
            $bits .= $value >= $mean ? '1' : '0';
        }

        $hex = '';
        foreach (str_split($bits, 4) as $nibble) {
            $hex .= base_convert($nibble, 2, 16);
        }

        return $hex;
    }

    public function hashDistance(?string $hashA, ?string $hashB): ?int
    {
        if ($hashA === null || $hashB === null || strlen($hashA) !== strlen($hashB)) {
            return null;
        }

        $binA = $this->hexToBinString($hashA);
        $binB = $this->hexToBinString($hashB);

        $distance = 0;
        for ($i = 0; $i < strlen($binA); $i++) {
            if ($binA[$i] !== $binB[$i]) {
                $distance++;
            }
        }

        return $distance;
    }

    public function embeddingSimilarity(?string $hashA, ?string $hashB): ?float
    {
        $distance = $this->hashDistance($hashA, $hashB);
        if ($distance === null) {
            return null;
        }

        $totalBits = self::HASH_SIZE * self::HASH_SIZE;

        return round(1 - ($distance / $totalBits), 4);
    }

    private function hexToBinString(string $hex): string
    {
        $bin = '';
        foreach (str_split($hex) as $char) {
            $bin .= str_pad(base_convert($char, 16, 2), 4, '0', STR_PAD_LEFT);
        }

        return $bin;
    }

    private function fetchBytes(string $fileRefOrUrl): ?string
    {
        try {
            if (Str::startsWith($fileRefOrUrl, ['http://', 'https://'])) {
                $response = Http::timeout(10)->get($fileRefOrUrl);

                return $response->successful() ? $response->body() : null;
            }

            if (Storage::disk('public')->exists($fileRefOrUrl)) {
                return Storage::disk('public')->get($fileRefOrUrl);
            }

            return null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
