<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * The device's own reported GPS accuracy (envelope `gps.accuracy_m`) — a
 * large accuracy radius means the fix itself is unreliable (indoors, no
 * sky view, or a spoofed/mocked location provider often reports an
 * implausibly large or suspiciously perfect value), so the distance checks
 * built on top of it deserve less trust.
 */
class GpsAccuracyRule implements QaRule
{
    // Tightened from 50m: OutletBaseline's own spot-level GPS radius is
    // typically only ~20m (see OutletSeeder), so a device fix any looser
    // than 5m can't reliably confirm the agent was actually at that exact
    // spot — the distance check built on top of it would be comparing a
    // precise baseline radius against an imprecise device reading.
    public const MAX_ACCEPTABLE_ACCURACY_M = 5.0;

    public function evaluate(Submission $submission): array
    {
        if ($submission->gps_accuracy_m === null) {
            return [];
        }

        $accuracy = (float) $submission->gps_accuracy_m;

        if ($accuracy > self::MAX_ACCEPTABLE_ACCURACY_M) {
            return [[
                'rule_name' => 'poor_gps_accuracy',
                'result' => QaFlagResult::Flag,
                'severity' => 'medium',
                'detail' => [
                    'reported_accuracy_m' => $accuracy,
                    'max_acceptable_accuracy_m' => self::MAX_ACCEPTABLE_ACCURACY_M,
                ],
            ]];
        }

        return [];
    }
}
