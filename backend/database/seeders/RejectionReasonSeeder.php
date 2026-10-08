<?php

namespace Database\Seeders;

use App\Models\RejectionReason;
use Illuminate\Database\Seeder;

class RejectionReasonSeeder extends Seeder
{
    /**
     * applies_to_quest_types must use App\Enums\QuestType's real values
     * ('quest', 'verification', 'survey') — every quest actually seeded in
     * this system is quest_type='quest' (see QuestSeeder), so a reason
     * missing that value from its list can never be selected by a reviewer
     * for any real submission. This previously listed stale mock-only
     * strings ('shelf_audit', 'price_check', 'agri_verify' — leftover from
     * frontend/src/api/mockData.ts's pre-backend placeholder data, which
     * were never real QuestType values), which meant EVERY reason silently
     * filtered out of frontend/src/components/ReasonCodeSelect.tsx's
     * dropdown for every real quest — the Reject modal had nothing to
     * select, so its Confirm button stayed permanently disabled
     * (required_if:decision,reject in ReviewSubmissionRequest).
     *
     * @var list<array{code: string, label: string, applies_to_quest_types: array<int, string>}>
     */
    public const REASONS = [
        ['code' => 'REUSED_PHOTO', 'label' => 'Possible Reused/Duplicate Photo', 'applies_to_quest_types' => ['quest', 'verification', 'survey']],
        ['code' => 'OUT_OF_GEOFENCE', 'label' => 'Submission Outside Geofence Radius', 'applies_to_quest_types' => ['quest', 'verification', 'survey']],
        ['code' => 'PRICE_OUTLIER', 'label' => 'Unusual Price Entry / Typo', 'applies_to_quest_types' => ['quest']],
        ['code' => 'BLURRY_PHOTO', 'label' => 'Photo Unclear or Obstructed', 'applies_to_quest_types' => ['quest', 'verification', 'survey']],
        ['code' => 'TIMESTAMP_MISMATCH', 'label' => 'Survey Completed Too Quickly / Timestamp Anomaly', 'applies_to_quest_types' => ['quest', 'verification', 'survey']],
        ['code' => 'WRONG_OUTLET_SPOT', 'label' => 'Photo Baseline Similarity Failure (Wrong Spot)', 'applies_to_quest_types' => ['quest', 'verification', 'survey']],
        ['code' => 'INCOMPLETE_ANSWERS', 'label' => 'Missing Required Fields or Invalid Input', 'applies_to_quest_types' => ['quest', 'verification', 'survey']],
    ];

    public function run(): void
    {
        foreach (self::REASONS as $reason) {
            RejectionReason::updateOrCreate(
                ['code' => $reason['code']],
                [
                    'label' => $reason['label'],
                    'applies_to_quest_types' => $reason['applies_to_quest_types'],
                    'active' => true,
                ]
            );
        }
    }
}
