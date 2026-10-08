<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Geo\Haversine;
use App\Services\Qa\QaRule;

/**
 * Two nested checks, both surfaced under the frontend's known
 * 'geofence_check' rule name (see FlagBadge.formatFlagRuleName):
 *  - the submission GPS vs. the outlet's own registered radius (tight)
 *  - the submission GPS vs. the quest's wider designated collection area
 */
class OutletGeofenceRule implements QaRule
{
    public function evaluate(Submission $submission): array
    {
        $flags = [];
        $outlet = $submission->outlet;
        $quest = $submission->quest;

        if ($outlet && $submission->gps_lat !== null && $submission->gps_lng !== null) {
            $distanceToOutlet = Haversine::distanceMeters(
                (float) $submission->gps_lat,
                (float) $submission->gps_lng,
                (float) $outlet->gps_lat,
                (float) $outlet->gps_lng,
            );

            if ($distanceToOutlet > $outlet->gps_radius_m) {
                $flags[] = [
                    'rule_name' => 'geofence_check',
                    'result' => QaFlagResult::Fail,
                    'severity' => 'high',
                    'detail' => [
                        'check' => 'outlet_radius',
                        'distance_m' => round($distanceToOutlet, 1),
                        'allowed_radius_m' => $outlet->gps_radius_m,
                    ],
                ];
            }
        }

        if ($quest && $quest->geofence_center_lat !== null && $quest->geofence_radius_m) {
            $distanceToQuest = Haversine::distanceMeters(
                (float) $submission->gps_lat,
                (float) $submission->gps_lng,
                (float) $quest->geofence_center_lat,
                (float) $quest->geofence_center_lng,
            );

            if ($distanceToQuest > $quest->geofence_radius_m) {
                $flags[] = [
                    'rule_name' => 'geofence_check',
                    'result' => QaFlagResult::Fail,
                    'severity' => 'high',
                    'detail' => [
                        'check' => 'quest_geofence',
                        'distance_m' => round($distanceToQuest, 1),
                        'allowed_radius_m' => $quest->geofence_radius_m,
                    ],
                ];
            }
        }

        return $flags;
    }
}
