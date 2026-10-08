<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Pricing\MarketPriceRangeResolver;
use App\Services\Qa\QaRule;

/**
 * "Above or below usual range" — compares each submitted price against its
 * expected range, resolved by MarketPriceRangeResolver from either the
 * ingestion payload itself (prices[].market_range_price) or, failing that,
 * the quest's admin-configured Baseline Management price range for that SKU.
 * If neither source has one, this price simply isn't checked — no historical
 * guess. Shared with the dashboard's own display (QaPresenter::priceItem) so
 * the flag and the number a reviewer sees always agree.
 */
class PriceRangeRule implements QaRule
{
    private const SEVERE_DEVIATION_PCT = 100.0; // more than double the range edge

    public function __construct(private MarketPriceRangeResolver $resolver)
    {
    }

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        foreach ($submission->prices as $price) {
            $range = $this->resolver->resolve($price, $submission->quest_id);
            if ($range === null) {
                continue;
            }

            $value = (float) $price->value;
            if ($value >= $range['min'] && $value <= $range['max']) {
                continue;
            }

            $direction = $value > $range['max'] ? 'above' : 'below';
            $referenceEdge = $direction === 'above' ? $range['max'] : $range['min'];
            $deviationPct = $referenceEdge > 0 ? abs($value - $referenceEdge) / $referenceEdge * 100 : null;
            $isSevere = $deviationPct !== null && $deviationPct > self::SEVERE_DEVIATION_PCT;

            $flags[] = [
                'rule_name' => 'price_range_outlier',
                'result' => $isSevere ? QaFlagResult::Fail : QaFlagResult::Flag,
                'severity' => $isSevere ? 'high' : 'medium',
                'detail' => [
                    'sku_id' => $price->sku_id,
                    'row_id' => $price->row_id,
                    'submitted_value' => $value,
                    'expected_min' => $range['min'],
                    'expected_max' => $range['max'],
                    'direction' => $direction,
                    'range_source' => $range['source'],
                    'deviation_pct' => $deviationPct !== null ? round($deviationPct, 1) : null,
                ],
            ];
        }

        return $flags;
    }
}
