<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\OutletBaseline;
use App\Models\User;
use Illuminate\Database\Seeder;

class OutletSeeder extends Seeder
{
    /**
     * Mirrors docs/outlet_and_baseline_reference_data.json exactly — the
     * six non-clustered Addis Ababa locations from the Metrix Beta Quest
     * Package brief (Product groups: edible oil, teff, red onion, beer).
     * GPS points are approximate real-world coordinates for each area, not
     * survey-grade — good enough for beta/demo geofence testing.
     *
     * Baseline photos are real product/shelf photos downloaded once into
     * local storage (backend/storage/app/public/seed-products/) rather
     * than hotlinked — see that folder's origin: Wikimedia Commons images,
     * fetched at seed-authoring time because the QA engine's own
     * PhotoAnalysisService/ImageEmbeddingService/ImageSimilarityService
     * each fetch a submission's photos server-side, and Wikimedia's CDN
     * both 403s requests with no descriptic User-Agent and rate-limits
     * repeat fetches — unsuitable for something the rule engine hits on
     * every submission. Local storage has neither problem and matches the
     * project's local-first design principle (see CLAUDE.md).
     */
    public const OUTLETS = [
        [
            'code' => 'OUT-BOLE-SUPERMARKET-001',
            'name' => 'Supermarket Chain A — Bole Branch',
            'chain' => 'Supermarket Chain A',
            'branch' => 'Bulbula / Mariam Mazoria, Bole',
            'outlet_type' => 'supermarket',
            'city' => 'Addis Ababa',
            'area' => 'Bole (Bulbula/Mariam Mazoria)',
            'gps_lat' => 8.99500,
            'gps_lng' => 38.79000,
            'gps_radius_m' => 150,
            'baselines' => [
                [
                    'spot_label' => 'shelf',
                    'baseline_gps_lat' => 8.99501,
                    'baseline_gps_lng' => 38.79001,
                    'baseline_gps_radius_m' => 25,
                    'baseline_photo_path' => 'seed-products/supermarket-shelf.jpg',
                    'notes' => 'Edible oil aisle, facing the beer cooler across the walkway.',
                ],
                [
                    'spot_label' => 'price_tag',
                    'baseline_gps_lat' => 8.99500,
                    'baseline_gps_lng' => 38.78999,
                    'baseline_gps_radius_m' => 20,
                    'baseline_photo_path' => 'seed-products/oil-bottle-sunflower-1l.jpg',
                    'notes' => 'Close-up reference for edible oil price tags, 1L/3L/5L shelf strip.',
                ],
            ],
        ],
        [
            'code' => 'OUT-SHOLA-MARKET-002',
            'name' => 'Shola Open Market — Grain & Produce Row',
            'chain' => null,
            'branch' => 'Shola',
            'outlet_type' => 'open_market',
            'city' => 'Addis Ababa',
            'area' => 'Shola',
            'gps_lat' => 9.01000,
            'gps_lng' => 38.79800,
            'gps_radius_m' => 200,
            'baselines' => [
                [
                    'spot_label' => 'shelf',
                    'baseline_gps_lat' => 9.01002,
                    'baseline_gps_lng' => 38.79799,
                    'baseline_gps_radius_m' => 30,
                    'baseline_photo_path' => 'seed-products/red-onion-pile-market.jpg',
                    'notes' => 'Open-air produce row — teff, red onion, and edible oil stalls side by side.',
                ],
                [
                    'spot_label' => 'price_tag',
                    'baseline_gps_lat' => 9.00999,
                    'baseline_gps_lng' => 38.79801,
                    'baseline_gps_radius_m' => 20,
                    'baseline_photo_path' => 'seed-products/teff-flour-product.jpg',
                    'notes' => 'Teff sack/measuring-unit close-up reference.',
                ],
            ],
        ],
        [
            'code' => 'OUT-MERCATO-WHOLESALE-003',
            'name' => 'Mercato Packaged Goods & Grain Section',
            'chain' => null,
            'branch' => 'Mercato',
            'outlet_type' => 'wholesale_market',
            'city' => 'Addis Ababa',
            'area' => 'Mercato',
            'gps_lat' => 9.03500,
            'gps_lng' => 38.74000,
            'gps_radius_m' => 250,
            'baselines' => [
                [
                    'spot_label' => 'shelf',
                    'baseline_gps_lat' => 9.03502,
                    'baseline_gps_lng' => 38.73998,
                    'baseline_gps_radius_m' => 35,
                    'baseline_photo_path' => 'seed-products/red-onion-store-display.jpg',
                    'notes' => 'Wholesale produce/grain display, bulk sacks and crates.',
                ],
                [
                    'spot_label' => 'price_tag',
                    'baseline_gps_lat' => 9.03499,
                    'baseline_gps_lng' => 38.74002,
                    'baseline_gps_radius_m' => 20,
                    'baseline_photo_path' => 'seed-products/oil-bottle-sunflower-1l.jpg',
                    'notes' => 'Wholesale edible oil price-tag/carton close-up reference.',
                ],
            ],
        ],
        [
            'code' => 'OUT-SARIS-GROCERY-004',
            'name' => 'Assigned Grocery & Nearby Fresh Vendor — Saris',
            'chain' => null,
            'branch' => 'Saris Addis Sefer',
            'outlet_type' => 'mini_market',
            'city' => 'Addis Ababa',
            'area' => 'Saris Addis Sefer',
            'gps_lat' => 8.95500,
            'gps_lng' => 38.76500,
            'gps_radius_m' => 150,
            'baselines' => [
                [
                    'spot_label' => 'shelf',
                    'baseline_gps_lat' => 8.95501,
                    'baseline_gps_lng' => 38.76501,
                    'baseline_gps_radius_m' => 25,
                    'baseline_photo_path' => 'seed-products/beer-bottles-shelf.jpg',
                    'notes' => 'Mini-market shelf/cooler — edible oil and beer.',
                ],
                [
                    'spot_label' => 'price_tag',
                    'baseline_gps_lat' => 8.95499,
                    'baseline_gps_lng' => 38.76499,
                    'baseline_gps_radius_m' => 20,
                    'baseline_photo_path' => 'seed-products/oil-bottle-sunflower-1l.jpg',
                    'notes' => 'Price-tag close-up reference, mini-market counter.',
                ],
            ],
        ],
        [
            'code' => 'OUT-CMCAYAT-GROCERY-005',
            'name' => 'Supermarket Chain B — CMC/Ayat Branch',
            'chain' => 'Supermarket Chain B',
            'branch' => 'CMC / Ayat',
            'outlet_type' => 'supermarket',
            'city' => 'Addis Ababa',
            'area' => 'CMC / Ayat',
            'gps_lat' => 9.02500,
            'gps_lng' => 38.85000,
            'gps_radius_m' => 150,
            'baselines' => [
                [
                    'spot_label' => 'shelf',
                    'baseline_gps_lat' => 9.02501,
                    'baseline_gps_lng' => 38.85001,
                    'baseline_gps_radius_m' => 25,
                    'baseline_photo_path' => 'seed-products/supermarket-shelf.jpg',
                    'notes' => 'Eastern Addis formal-retail shelf — edible oil and beer.',
                ],
                [
                    'spot_label' => 'price_tag',
                    'baseline_gps_lat' => 9.02499,
                    'baseline_gps_lng' => 38.84999,
                    'baseline_gps_radius_m' => 20,
                    'baseline_photo_path' => 'seed-products/beer-bottle-330ml.jpg',
                    'notes' => '330ml beer bottle price-tag close-up reference.',
                ],
            ],
        ],
        [
            'code' => 'OUT-KAZANCHIS-BEER-006',
            'name' => 'Licensed Beer Outlet — Kazanchis',
            'chain' => null,
            'branch' => 'Kazanchis / Piassa',
            'outlet_type' => 'licensed_grocery',
            'city' => 'Addis Ababa',
            'area' => 'Kazanchis',
            'gps_lat' => 9.01500,
            'gps_lng' => 38.76500,
            'gps_radius_m' => 100,
            'baselines' => [
                [
                    'spot_label' => 'shelf',
                    'baseline_gps_lat' => 9.01501,
                    'baseline_gps_lng' => 38.76501,
                    'baseline_gps_radius_m' => 20,
                    'baseline_photo_path' => 'seed-products/beer-bottles-shelf.jpg',
                    'notes' => 'Fridge/shelf beer display, licensed grocery/bar counter.',
                ],
                [
                    'spot_label' => 'price_tag',
                    'baseline_gps_lat' => 9.01499,
                    'baseline_gps_lng' => 38.76499,
                    'baseline_gps_radius_m' => 15,
                    'baseline_photo_path' => 'seed-products/beer-bottle-330ml.jpg',
                    'notes' => '330ml bottle close-up reference.',
                ],
            ],
        ],
    ];

    public function run(): void
    {
        $captureUser = User::first();

        foreach (self::OUTLETS as $outletData) {
            $baselines = $outletData['baselines'];
            unset($outletData['baselines']);

            $outlet = Outlet::updateOrCreate(
                ['code' => $outletData['code']],
                [...$outletData, 'active' => true]
            );

            foreach ($baselines as $baseline) {
                OutletBaseline::updateOrCreate(
                    ['outlet_id' => $outlet->id, 'spot_label' => $baseline['spot_label']],
                    [
                        'baseline_gps_lat' => $baseline['baseline_gps_lat'],
                        'baseline_gps_lng' => $baseline['baseline_gps_lng'],
                        'baseline_gps_radius_m' => $baseline['baseline_gps_radius_m'],
                        'baseline_photo_path' => $baseline['baseline_photo_path'],
                        'captured_by' => $captureUser?->id,
                        'captured_at' => now(),
                        'notes' => $baseline['notes'],
                    ]
                );
            }
        }
    }
}
