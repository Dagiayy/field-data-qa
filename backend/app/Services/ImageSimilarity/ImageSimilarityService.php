<?php

namespace App\Services\ImageSimilarity;

/**
 * Abstraction over "how similar are these two photos" so the QA rule engine
 * never talks to a concrete implementation directly. Today this is answered
 * locally with a perceptual hash (see LocalPerceptualHashService). Later,
 * swapping in a call to the Python image-service (open_clip embeddings) for
 * the `embeddingSimilarity` half is a binding change in AppServiceProvider,
 * not a rewrite of any rule class.
 */
interface ImageSimilarityService
{
    /**
     * A stable perceptual hash for a single image, addressed by a local
     * storage path or a remote URL. Returns null if the image could not be
     * fetched/decoded.
     */
    public function hash(string $fileRefOrUrl): ?string;

    /**
     * Hamming distance between two previously computed hashes (0 = identical,
     * higher = more different). Null if either hash is null.
     */
    public function hashDistance(?string $hashA, ?string $hashB): ?int;

    /**
     * A 0..1 "how similar are these two photos" score. Until a real
     * embedding model is wired in, this is derived from the hash distance.
     */
    public function embeddingSimilarity(?string $hashA, ?string $hashB): ?float;
}
