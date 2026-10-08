<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Media;
use App\Models\Submission;
use App\Services\Geo\Haversine;
use App\Services\Qa\QaRule;

/**
 * Flags a photo whose capture GPS is suspiciously identical to a photo from
 * a DIFFERENT submission AT A DIFFERENT OUTLET — a real camera/GPS fix
 * essentially never lands on the exact same coordinate twice, so two
 * unrelated outlets sharing one only makes sense if the location was
 * mocked/replayed rather than actually visited.
 *
 * Repeat visits to the SAME outlet are deliberately excluded: the shelf/
 * price-tag spot doesn't move, so an agent legitimately revisiting that
 * outlet next week will land on essentially the same GPS point every time
 * — that's expected, not suspicious, and flagging it would just be noise
 * on every routine repeat visit.
 */
class DuplicateLocationRule implements QaRule
{
    private const DUPLICATE_DISTANCE_METERS = 2.0;

    // ~0.002 degrees is a generous ~220m box used only to pre-filter
    // candidates before the precise Haversine check below.
    private const BOUNDING_BOX_DEGREES = 0.002;

    public function evaluate(Submission $submission): array
    {
        $flags = [];

        foreach ($submission->media as $media) {
            if ($media->gps_at_capture_lat === null || $media->gps_at_capture_lng === null) {
                continue;
            }

            $lat = (float) $media->gps_at_capture_lat;
            $lng = (float) $media->gps_at_capture_lng;

            $candidates = Media::query()
                ->where('submission_id', '!=', $submission->id)
                ->whereNotNull('gps_at_capture_lat')
                ->whereNotNull('gps_at_capture_lng')
                ->whereBetween('gps_at_capture_lat', [$lat - self::BOUNDING_BOX_DEGREES, $lat + self::BOUNDING_BOX_DEGREES])
                ->whereBetween('gps_at_capture_lng', [$lng - self::BOUNDING_BOX_DEGREES, $lng + self::BOUNDING_BOX_DEGREES])
                ->whereHas('submission', fn ($q) => $q->where('outlet_id', '!=', $submission->outlet_id))
                ->get();

            foreach ($candidates as $other) {
                $distance = Haversine::distanceMeters(
                    $lat,
                    $lng,
                    (float) $other->gps_at_capture_lat,
                    (float) $other->gps_at_capture_lng,
                );

                if ($distance <= self::DUPLICATE_DISTANCE_METERS) {
                    $flags[] = [
                        'rule_name' => 'duplicate_gps_location',
                        'result' => QaFlagResult::Flag,
                        'severity' => 'medium',
                        'detail' => [
                            'media_ref' => $media->media_ref,
                            'matched_submission_id' => $other->submission_id,
                            'matched_media_ref' => $other->media_ref,
                            'distance_m' => round($distance, 2),
                        ],
                    ];
                    break;
                }
            }
        }

        return $flags;
    }
}
