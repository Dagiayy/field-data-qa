<?php

namespace Database\Seeders;

use App\Enums\QuestType;
use App\Models\Outlet;
use App\Models\Quest;
use App\Services\Trust\TrustScoreCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The six Metrix Beta Quest Package locations (Product groups: edible oil,
 * teff, red onion, beer — see docs/Metrix_Beta_Quest_Package.pdf). Each
 * quest's geofence is centered on its one outlet (unlike a multi-branch
 * quest) with a generous few-hundred-meter radius for real-world GPS drift
 * — tight enough that the wider quest-level check still means something,
 * wide enough it never fires ahead of the outlet/baseline's own tighter
 * checks (see App\Services\Qa\Rules\OutletGeofenceRule).
 *
 * required_question_ids only lists each module's base question_id — a
 * repeat_table row's question_id is the same across rows (see
 * RequiredFieldsRule: it checks presence anywhere in the submission's
 * answers, not per-row), so one id per field covers every brand/SKU row.
 */
class QuestSeeder extends Seeder
{
    private const OIL_REQUIRED = [
        'oil_brand', 'oil_pack_size', 'oil_available', 'oil_price',
        'oil_shelf_visibility', 'oil_stock_status', 'oil_shelf_photo', 'oil_price_photo',
    ];

    private const TEFF_REQUIRED = [
        'teff_variety', 'teff_available', 'teff_price', 'teff_unit',
        'teff_price_type', 'teff_quality_grade', 'teff_photo',
    ];

    private const ONION_REQUIRED = [
        'onion_origin', 'onion_available', 'onion_price', 'onion_unit',
        'onion_size_quality', 'onion_price_type', 'onion_photo',
    ];

    private const BEER_REQUIRED = [
        'beer_brand', 'beer_sku', 'beer_available', 'beer_price',
        'beer_cold_availability', 'beer_visibility', 'beer_photo',
    ];

    public function run(): void
    {
        $this->makeQuest(
            formCode: 'QST-SUPERMARKET-BOLE-001',
            title: 'Supermarket Product Scan - Bole',
            outletCode: 'OUT-BOLE-SUPERMARKET-001',
            centerLat: 8.99500,
            centerLng: 38.79000,
            radiusM: 400,
            modules: [self::OIL_REQUIRED, self::BEER_REQUIRED],
            reward: 200.00,
        );

        $this->makeQuest(
            formCode: 'QST-OPENMARKET-SHOLA-002',
            title: 'Open Market Staple Scan - Shola',
            outletCode: 'OUT-SHOLA-MARKET-002',
            centerLat: 9.01000,
            centerLng: 38.79800,
            radiusM: 500,
            modules: [self::TEFF_REQUIRED, self::ONION_REQUIRED, self::OIL_REQUIRED],
            reward: 280.00,
        );

        $this->makeQuest(
            formCode: 'QST-WHOLESALE-MERCATO-003',
            title: 'Wholesale/Market Price Scan - Mercato',
            outletCode: 'OUT-MERCATO-WHOLESALE-003',
            centerLat: 9.03500,
            centerLng: 38.74000,
            radiusM: 600,
            modules: [self::OIL_REQUIRED, self::TEFF_REQUIRED, self::ONION_REQUIRED],
            reward: 300.00,
        );

        $this->makeQuest(
            formCode: 'QST-MIXEDRETAIL-SARIS-004',
            title: 'Mixed Retail Scan - Saris/Gotera',
            outletCode: 'OUT-SARIS-GROCERY-004',
            centerLat: 8.95500,
            centerLng: 38.76500,
            radiusM: 400,
            modules: [self::OIL_REQUIRED, self::BEER_REQUIRED, self::ONION_REQUIRED],
            reward: 260.00,
        );

        $this->makeQuest(
            formCode: 'QST-GROCERY-CMCAYAT-005',
            title: 'Supermarket/Grocery Scan - CMC/Ayat',
            outletCode: 'OUT-CMCAYAT-GROCERY-005',
            centerLat: 9.02500,
            centerLng: 38.85000,
            radiusM: 400,
            modules: [self::OIL_REQUIRED, self::BEER_REQUIRED],
            reward: 200.00,
        );

        $this->makeQuest(
            formCode: 'QST-BEERSCAN-KAZANCHIS-006',
            title: 'Beer Availability Scan - Piassa/Kazanchis',
            outletCode: 'OUT-KAZANCHIS-BEER-006',
            centerLat: 9.01500,
            centerLng: 38.76500,
            radiusM: 300,
            modules: [self::BEER_REQUIRED],
            reward: 150.00,
        );
    }

    /**
     * @param  list<list<string>>  $modules
     */
    private function makeQuest(
        string $formCode,
        string $title,
        string $outletCode,
        float $centerLat,
        float $centerLng,
        int $radiusM,
        array $modules,
        float $reward,
    ): void {
        $required = ['branch_select', 'confirm_branch'];
        foreach ($modules as $moduleFields) {
            $required = [...$required, ...$moduleFields];
        }
        $required[] = 'confirm';

        $outlet = Outlet::where('code', $outletCode)->first();

        Quest::updateOrCreate(
            ['form_code' => $formCode],
            [
                'title' => $title,
                'quest_type' => QuestType::Quest,
                'outlet_id' => $outlet?->id,
                'config_version' => $formCode.'_v1',
                'geofence_center_lat' => $centerLat,
                'geofence_center_lng' => $centerLng,
                'geofence_radius_m' => $radiusM,
                'collection_window_start' => Carbon::parse('2026-07-01'),
                'collection_window_end' => Carbon::parse('2026-09-30'),
                'max_submissions_per_outlet' => null,
                'reward_amount' => $reward,
                'reward_currency' => 'ETB',
                'required_question_ids' => $required,
                'trust_score_weights' => TrustScoreCalculator::DEFAULT_WEIGHTS,
                // expected_duration_min/max_seconds intentionally left null
                // — a fresh Baseline Management setup task (see
                // SurveyDurationRule), not something to guess a number for here.
                'active' => true,
            ]
        );
    }
}
