<?php

namespace App\Services\ImageSimilarity;

/**
 * Abstraction over "compute a semantic vision embedding for this photo" so
 * the QA rule engine never talks to the image-service's /embed endpoint
 * directly. Separate from ImageSimilarityService (perceptual hash): a hash
 * is cheap and good for exact/near-exact reuse detection (DuplicatePhotoRule),
 * but a bad tool for "is this the same shelf/spot as the baseline" — that's
 * what the embedding (OpenCLIP, computed locally) is for.
 *
 * Same "compute once, cache on the row, diff locally" pattern as the hash
 * service: embed() is called once per photo at ingestion/baseline-registration
 * time; cosineSimilarity() is pure math over two already-computed vectors, no
 * network round trip needed to compare.
 */
interface ImageEmbeddingService
{
    /**
     * A vision embedding vector for a single image, addressed by a local
     * storage path or a remote URL. Returns null if the image could not be
     * fetched/embedded (e.g. image-service down).
     *
     * @return list<float>|null
     */
    public function embed(string $fileRefOrUrl): ?array;

    /**
     * Cosine similarity (-1..1, in practice ~0..1 for normalized embeddings
     * of real photos) between two previously computed embedding vectors.
     * Null if either vector is null/empty or their dimensions don't match.
     *
     * @param  list<float>|null  $a
     * @param  list<float>|null  $b
     */
    public function cosineSimilarity(?array $a, ?array $b): ?float;
}
