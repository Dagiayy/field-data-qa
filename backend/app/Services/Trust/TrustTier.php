<?php

namespace App\Services\Trust;

/**
 * Trust Score tier bands, shared between the single-agent detail endpoint
 * and the all-agents list. A judgment call (not specified anywhere in the
 * QA design doc) — Gold/Silver/Bronze/Flagged just need SOME ordering for
 * the dashboard's tier badge, and these leave meaningful room between
 * "flagged for review" and "reliably clean".
 */
class TrustTier
{
    private const THRESHOLDS = [
        'Gold' => 90,
        'Silver' => 75,
        'Bronze' => 60,
    ];

    // No submissions scored yet — a neutral middle default rather than
    // rewarding (Gold) or penalizing (Flagged) an agent with no track
    // record either way.
    private const NO_HISTORY_TIER = 'Bronze';

    public static function forScore(?float $overallTrustScore): string
    {
        if ($overallTrustScore === null) {
            return self::NO_HISTORY_TIER;
        }

        foreach (self::THRESHOLDS as $tier => $minScore) {
            if ($overallTrustScore >= $minScore) {
                return $tier;
            }
        }

        return 'Flagged';
    }
}
