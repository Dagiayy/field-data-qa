<?php

namespace App\Services\Pricing;

use App\Models\Price;
use App\Models\QuestBaselinePrice;

/**
 * Resolves the expected price range ("MarketRangePrice") for a submitted
 * price. Two sources, checked in order:
 *   1. The incoming ingestion payload itself (prices[].market_range_price)
 *      — always wins when present, since it's specific to this submission.
 *   2. The quest's admin-configured Baseline Management price range for
 *      this SKU (QuestBaselinePrice), set up once per quest_id and reused
 *      for every future submission to that quest — added so a quest can be
 *      price-validated even when individual submissions don't carry a range.
 * If neither source has one, this returns null and the price simply has no
 * expected-range comparison (surfaced to reviewers as "no baseline" rather
 * than a silent pass — see SubmissionReview's price section).
 */
class MarketPriceRangeResolver
{
    /**
     * $questId is accepted explicitly rather than read off $price->submission
     * — that relation isn't set from the hasMany side when a submission's
     * prices are eager-loaded (Submission::prices()), so touching it here
     * would fire one extra lazy query per price. Both call sites
     * (PriceRangeRule, QaPresenter::priceItem) already have the owning
     * Submission in scope.
     */
    public function resolve(Price $price, ?int $questId = null): ?array
    {
        if ($price->market_range_min !== null && $price->market_range_max !== null) {
            return [
                'min' => (float) $price->market_range_min,
                'max' => (float) $price->market_range_max,
                'source' => 'payload',
            ];
        }

        if ($questId === null || $price->sku_id === null) {
            return null;
        }

        $baseline = QuestBaselinePrice::where('quest_id', $questId)
            ->where('sku_id', $price->sku_id)
            ->first();

        if (! $baseline) {
            return null;
        }

        return [
            'min' => (float) $baseline->min,
            'max' => (float) $baseline->max,
            'source' => 'quest_baseline',
        ];
    }

    /**
     * Parses the envelope's "market_range_price" field: either a range
     * like "350-450" or a single value like "410" (min == max).
     *
     * @return array{min: float, max: float}|null
     */
    public static function parse(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $value = (float) $raw;

            return ['min' => $value, 'max' => $value];
        }

        if (is_string($raw) && preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*-\s*(-?\d+(?:\.\d+)?)\s*$/', $raw, $matches)) {
            $min = (float) $matches[1];
            $max = (float) $matches[2];

            return ['min' => min($min, $max), 'max' => max($min, $max)];
        }

        return null;
    }
}
