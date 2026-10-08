// Starter payloads for the manual ingestion tester (TestIngestion.tsx) —
// loading one gives a known-good starting point to edit from, rather than
// writing the whole envelope from scratch. submission_id is regenerated on
// load (see TestIngestion.tsx) so repeated submits don't collide with the
// idempotency key.
//
// These match the six Metrix Beta Quest Package quests seeded by
// QuestSeeder/OutletSeeder (edible oil, teff, red onion, beer across six
// non-clustered Addis Ababa locations) — reseed with
// `php artisan migrate:fresh --seed` if these quest_id/outlet_id values
// ever stop resolving.
//
// Repeat-row modules (oil brand×pack, teff variety, red onion origin, beer
// brand) follow the same convention the original oil sample established:
// a `*_rows` repeat_table answer carries one summary row per SKU
// (row_id + label fields + an `available` flag for the dashboard's
// generic repeat-table renderer), and the fields that genuinely vary
// per-row (brand/variety/origin, availability, price, condition, close-up
// photo) are separate flat answers scoped by that same row_id — exactly
// how price/photo answers were already row-scoped. Fields that apply once
// per visit regardless of how many SKUs were checked (overall shelf
// photo/visibility, price type, trader notes) are submission-scoped, not
// repeated per row.
//
// Photo file_ref values point at backend/storage/app/public/seed-products/
// — real product/shelf photos (sourced from Wikimedia Commons, downloaded
// once into local storage rather than hotlinked; see OutletSeeder's
// docblock for why hotlinking to an external CDN doesn't work reliably for
// something the QA rule engine fetches server-side on every submission).
// Using a local storage-relative path as file_ref (not a full URL) also
// matches what the real Mini App pipeline will eventually send once Phase
// 2's multipart upload contract lands (see project CLAUDE.md) — the
// backend resolves both the same way (App\Support\QaPresenter::resolveUrl).
// All are above PhotoResolutionRule's 1280x720 minimum.
//
// For the "clean" samples, exactly one photo per submission is deliberately
// pinned to a real match against its outlet's registered baseline: same
// image bytes AND a gps_at_capture equal to that baseline's own point,
// which resolveBaseline() (BaselineLocationRule) picks up via nearest-
// distance fallback. Every other photo in that submission uses a genuinely
// different real image (reusing the same bytes across two different
// media_refs in one submission is exactly what DuplicatePhotoRule exists to
// catch) — those may still soft-flag against the single static baseline
// photo on file, which is realistic, expected QA behavior with real photos
// through the real analysis pipeline, not a bug in this sample data.

export interface SamplePayload {
  key: string;
  label: string;
  description: string;
  envelope: Record<string, unknown>;
}

export const SAMPLE_PAYLOADS: SamplePayload[] = [
  {
    key: 'beer_scan_kazanchis',
    label: 'Sample — Beer Availability Scan (Kazanchis)',
    description: 'Single-module beer check at a licensed grocery outlet. One brand, one photo, deliberately matched to the outlet\'s own baseline. Expect only the informational "no duration baseline configured" flag — no baseline has been set for this quest yet in Baseline Management.',
    envelope: {
      submission_id: 'b1a1c001-1111-4a11-9a11-000000000001',
      quest_id: 'QST-BEERSCAN-KAZANCHIS-006',
      config_version: 'QST-BEERSCAN-KAZANCHIS-006_v1',
      agent_id: 'AGT-30061',
      outlet_id: 'OUT-KAZANCHIS-BEER-006',
      timestamps: {
        survey_start_at: '2026-07-24T16:10:00+03:00',
        survey_end_at: '2026-07-24T16:14:30+03:00',
        submitted_at: '2026-07-24T16:14:42+03:00',
      },
      gps: { lat: 9.01502, lng: 38.76498, accuracy_m: 4 },
      answers: [
        { question_id: 'branch_select', input_type: 'dropdown', value: 'OUT-KAZANCHIS-BEER-006' },
        { question_id: 'confirm_branch', input_type: 'single_choice', value: 'Yes' },
        {
          question_id: 'beer_rows',
          input_type: 'repeat_table',
          value: [{ row_id: 'st_george', brand: 'St. George', pack_size: '330ml bottle', available: true }],
        },
        { question_id: 'beer_brand', input_type: 'single_choice', row_id: 'st_george', value: 'St. George' },
        { question_id: 'beer_sku', input_type: 'text', row_id: 'st_george', value: '330ml bottle' },
        { question_id: 'beer_available', input_type: 'single_choice', row_id: 'st_george', value: 'Yes' },
        { question_id: 'beer_price', input_type: 'numeric_price', row_id: 'st_george', value: 68, currency: 'ETB' },
        { question_id: 'beer_cold_availability', input_type: 'single_choice', row_id: 'st_george', value: 'Cold' },
        { question_id: 'beer_visibility', input_type: 'single_choice', row_id: 'st_george', value: 'Fridge visible' },
        { question_id: 'beer_competing_visible', input_type: 'single_choice', value: 'Yes' },
        { question_id: 'beer_photo', input_type: 'photo', row_id: 'st_george', media_ref: 'media_001' },
        { question_id: 'beer_photo_not_allowed', input_type: 'single_choice', value: 'No' },
        { question_id: 'confirm', input_type: 'checkbox', value: true },
      ],
      media: [
        {
          // gps_at_capture matches OUT-KAZANCHIS-BEER-006's "price_tag"
          // baseline point exactly, and reuses that baseline's own photo —
          // deterministically resolves to (and visually matches) that spot
          // rather than the outlet's other ("shelf") baseline.
          media_ref: 'media_001', question_id: 'beer_photo', row_id: 'st_george', media_type: 'image',
          file_ref: 'seed-products/beer-bottle-330ml.jpg',
          captured_at: '2026-07-24T16:12:30+03:00',
          gps_at_capture: { lat: 9.01499, lng: 38.76499 },
        },
      ],
      prices: [
        { question_id: 'beer_price', sku_id: 'SKU-BEER-STGEORGE-330ML', row_id: 'st_george', value: 68, currency: 'ETB', unit: 'per bottle' },
      ],
      client_app_version: '2.5.0',
    },
  },

  {
    key: 'supermarket_scan_bole',
    label: 'Complex — Supermarket Product Scan (Bole)',
    description: 'Modern retail: 3 edible-oil brand/pack rows + 2 beer brand rows in one visit, each with its own price and a genuinely distinct close-up photo. All submission-level data (prices, GPS, timing) is clean — but these are real photos run through the real QA image pipeline, so expect some genuine photo-quality/baseline-similarity flags (blur, legibility, etc.) on the rows not pinned to a registered baseline photo, plus the informational "no duration baseline configured" flag. That\'s the pipeline doing its job on real content, not a bug in the sample.',
    envelope: {
      submission_id: 'b1a1c002-2222-4a22-9a22-000000000002',
      quest_id: 'QST-SUPERMARKET-BOLE-001',
      config_version: 'QST-SUPERMARKET-BOLE-001_v1',
      agent_id: 'AGT-30012',
      outlet_id: 'OUT-BOLE-SUPERMARKET-001',
      timestamps: {
        survey_start_at: '2026-07-24T10:05:00+03:00',
        survey_end_at: '2026-07-24T10:22:40+03:00',
        submitted_at: '2026-07-24T10:22:55+03:00',
      },
      gps: { lat: 8.99502, lng: 38.79002, accuracy_m: 5 },
      answers: [
        { question_id: 'branch_select', input_type: 'dropdown', value: 'OUT-BOLE-SUPERMARKET-001' },
        { question_id: 'confirm_branch', input_type: 'single_choice', value: 'Yes' },

        // --- Edible Oil module: Orkid 1L, Tena 3L, Omar 5L ---
        {
          question_id: 'oil_rows',
          input_type: 'repeat_table',
          value: [
            { row_id: 'orkid_1l', brand: 'Orkid', pack_size: '1L', available: true },
            { row_id: 'tena_3l', brand: 'Tena', pack_size: '3L', available: true },
            { row_id: 'omar_5l', brand: 'Omar', pack_size: '5L', available: true },
          ],
        },
        { question_id: 'oil_brand', input_type: 'single_choice', row_id: 'orkid_1l', value: 'Orkid' },
        { question_id: 'oil_pack_size', input_type: 'single_choice', row_id: 'orkid_1l', value: '1L' },
        { question_id: 'oil_available', input_type: 'single_choice', row_id: 'orkid_1l', value: 'Yes' },
        { question_id: 'oil_price', input_type: 'numeric_price', row_id: 'orkid_1l', value: 430, currency: 'ETB' },
        { question_id: 'oil_stock_status', input_type: 'single_choice', row_id: 'orkid_1l', value: 'Normal' },
        { question_id: 'oil_price_photo', input_type: 'photo', row_id: 'orkid_1l', media_ref: 'media_001' },

        { question_id: 'oil_brand', input_type: 'single_choice', row_id: 'tena_3l', value: 'Tena' },
        { question_id: 'oil_pack_size', input_type: 'single_choice', row_id: 'tena_3l', value: '3L' },
        { question_id: 'oil_available', input_type: 'single_choice', row_id: 'tena_3l', value: 'Yes' },
        { question_id: 'oil_price', input_type: 'numeric_price', row_id: 'tena_3l', value: 1200, currency: 'ETB' },
        { question_id: 'oil_stock_status', input_type: 'single_choice', row_id: 'tena_3l', value: 'Low' },
        { question_id: 'oil_price_photo', input_type: 'photo', row_id: 'tena_3l', media_ref: 'media_002' },

        { question_id: 'oil_brand', input_type: 'single_choice', row_id: 'omar_5l', value: 'Omar' },
        { question_id: 'oil_pack_size', input_type: 'single_choice', row_id: 'omar_5l', value: '5L' },
        { question_id: 'oil_available', input_type: 'single_choice', row_id: 'omar_5l', value: 'Yes' },
        { question_id: 'oil_price', input_type: 'numeric_price', row_id: 'omar_5l', value: 1950, currency: 'ETB' },
        { question_id: 'oil_stock_status', input_type: 'single_choice', row_id: 'omar_5l', value: 'Normal' },
        { question_id: 'oil_price_photo', input_type: 'photo', row_id: 'omar_5l', media_ref: 'media_003' },

        // Once per visit — one shelf shot covers all three oil SKUs.
        { question_id: 'oil_shelf_visibility', input_type: 'single_choice', value: 'Good' },
        { question_id: 'oil_shelf_photo', input_type: 'photo', media_ref: 'media_004' },
        { question_id: 'oil_promotion_display', input_type: 'single_choice', value: 'No' },

        // --- Beer module: Dashen, Heineken ---
        {
          question_id: 'beer_rows',
          input_type: 'repeat_table',
          value: [
            { row_id: 'dashen', brand: 'Dashen', pack_size: '330ml bottle', available: true },
            { row_id: 'heineken', brand: 'Heineken', pack_size: '330ml bottle', available: true },
          ],
        },
        { question_id: 'beer_brand', input_type: 'single_choice', row_id: 'dashen', value: 'Dashen' },
        { question_id: 'beer_sku', input_type: 'text', row_id: 'dashen', value: '330ml bottle' },
        { question_id: 'beer_available', input_type: 'single_choice', row_id: 'dashen', value: 'Yes' },
        { question_id: 'beer_price', input_type: 'numeric_price', row_id: 'dashen', value: 62, currency: 'ETB' },
        { question_id: 'beer_cold_availability', input_type: 'single_choice', row_id: 'dashen', value: 'Cold' },
        { question_id: 'beer_visibility', input_type: 'single_choice', row_id: 'dashen', value: 'Fridge visible' },
        { question_id: 'beer_photo', input_type: 'photo', row_id: 'dashen', media_ref: 'media_005' },

        { question_id: 'beer_brand', input_type: 'single_choice', row_id: 'heineken', value: 'Heineken' },
        { question_id: 'beer_sku', input_type: 'text', row_id: 'heineken', value: '330ml bottle' },
        { question_id: 'beer_available', input_type: 'single_choice', row_id: 'heineken', value: 'Yes' },
        { question_id: 'beer_price', input_type: 'numeric_price', row_id: 'heineken', value: 95, currency: 'ETB' },
        { question_id: 'beer_cold_availability', input_type: 'single_choice', row_id: 'heineken', value: 'Cold' },
        { question_id: 'beer_visibility', input_type: 'single_choice', row_id: 'heineken', value: 'Fridge visible' },
        { question_id: 'beer_photo', input_type: 'photo', row_id: 'heineken', media_ref: 'media_006' },

        { question_id: 'beer_competing_visible', input_type: 'single_choice', value: 'Yes' },
        { question_id: 'beer_photo_not_allowed', input_type: 'single_choice', value: 'No' },
        { question_id: 'confirm', input_type: 'checkbox', value: true },
      ],
      media: [
        // orkid_1l's photo matches OUT-BOLE-SUPERMARKET-001's "price_tag"
        // baseline exactly (same file + same GPS point) — a clean,
        // deterministic baseline match. tena_3l/omar_5l use genuinely
        // different real photos (not the same bytes reused across rows,
        // which DuplicatePhotoRule would correctly flag) at nearby-but-not-
        // identical points, same as a real agent naturally would — those
        // may still soft-flag against the single static baseline, which is
        // expected, realistic behavior (see BaselineVisualSimilarityRule),
        // not a bug in this sample.
        { media_ref: 'media_001', question_id: 'oil_price_photo', row_id: 'orkid_1l', media_type: 'image', file_ref: 'seed-products/oil-bottle-sunflower-1l.jpg', captured_at: '2026-07-24T10:10:00+03:00', gps_at_capture: { lat: 8.99500, lng: 38.78999 } },
        { media_ref: 'media_002', question_id: 'oil_price_photo', row_id: 'tena_3l', media_type: 'image', file_ref: 'seed-products/oil-bottle-plain-3l.jpg', captured_at: '2026-07-24T10:11:20+03:00', gps_at_capture: { lat: 8.99503, lng: 38.78998 } },
        { media_ref: 'media_003', question_id: 'oil_price_photo', row_id: 'omar_5l', media_type: 'image', file_ref: 'seed-products/oil-bottle-5l.jpg', captured_at: '2026-07-24T10:12:40+03:00', gps_at_capture: { lat: 8.99498, lng: 38.79002 } },
        // Matches the "shelf" baseline exactly the same way.
        { media_ref: 'media_004', question_id: 'oil_shelf_photo', media_type: 'image', file_ref: 'seed-products/supermarket-shelf.jpg', captured_at: '2026-07-24T10:13:30+03:00', gps_at_capture: { lat: 8.99501, lng: 38.79001 } },
        { media_ref: 'media_005', question_id: 'beer_photo', row_id: 'dashen', media_type: 'image', file_ref: 'seed-products/beer-bottle-330ml.jpg', captured_at: '2026-07-24T10:18:00+03:00', gps_at_capture: { lat: 8.99502, lng: 38.79002 } },
        { media_ref: 'media_006', question_id: 'beer_photo', row_id: 'heineken', media_type: 'image', file_ref: 'seed-products/beer-bottle-castel.jpg', captured_at: '2026-07-24T10:19:10+03:00', gps_at_capture: { lat: 8.99503, lng: 38.79003 } },
      ],
      prices: [
        { question_id: 'oil_price', sku_id: 'SKU-OIL-ORKID-1L', row_id: 'orkid_1l', value: 430, currency: 'ETB', unit: 'per bottle' },
        { question_id: 'oil_price', sku_id: 'SKU-OIL-TENA-3L', row_id: 'tena_3l', value: 1200, currency: 'ETB', unit: 'per bottle' },
        { question_id: 'oil_price', sku_id: 'SKU-OIL-OMAR-5L', row_id: 'omar_5l', value: 1950, currency: 'ETB', unit: 'per bottle' },
        { question_id: 'beer_price', sku_id: 'SKU-BEER-DASHEN-330ML', row_id: 'dashen', value: 62, currency: 'ETB', unit: 'per bottle' },
        { question_id: 'beer_price', sku_id: 'SKU-BEER-HEINEKEN-330ML', row_id: 'heineken', value: 95, currency: 'ETB', unit: 'per bottle' },
      ],
      client_app_version: '2.5.0',
    },
  },

  {
    key: 'openmarket_scan_shola',
    label: 'Complex — Open Market Staple Scan (Shola)',
    description: 'Open-market staples: 2 teff varieties + red onion + edible oil in one trader visit. Submission-level data is clean; same photo-pipeline caveat as the other complex samples — real photos can genuinely trigger photo-quality/baseline-similarity flags, plus the informational "no duration baseline configured" flag.',
    envelope: {
      submission_id: 'b1a1c003-3333-4a33-9a33-000000000003',
      quest_id: 'QST-OPENMARKET-SHOLA-002',
      config_version: 'QST-OPENMARKET-SHOLA-002_v1',
      agent_id: 'AGT-30022',
      outlet_id: 'OUT-SHOLA-MARKET-002',
      timestamps: {
        survey_start_at: '2026-07-24T08:30:00+03:00',
        survey_end_at: '2026-07-24T08:52:15+03:00',
        submitted_at: '2026-07-24T08:52:30+03:00',
      },
      gps: { lat: 9.01001, lng: 38.79799, accuracy_m: 5 },
      answers: [
        { question_id: 'branch_select', input_type: 'dropdown', value: 'OUT-SHOLA-MARKET-002' },
        { question_id: 'confirm_branch', input_type: 'single_choice', value: 'Yes' },

        // --- Teff module: White, Red ---
        {
          question_id: 'teff_rows',
          input_type: 'repeat_table',
          value: [
            { row_id: 'white_teff', pack_size: 'White teff', available: true },
            { row_id: 'red_teff', pack_size: 'Red teff', available: true },
          ],
        },
        { question_id: 'teff_variety', input_type: 'single_choice', row_id: 'white_teff', value: 'White' },
        { question_id: 'teff_available', input_type: 'single_choice', row_id: 'white_teff', value: 'Yes' },
        { question_id: 'teff_price', input_type: 'numeric_price', row_id: 'white_teff', value: 105, currency: 'ETB' },
        { question_id: 'teff_unit', input_type: 'single_choice', row_id: 'white_teff', value: 'kg' },
        { question_id: 'teff_quality_grade', input_type: 'single_choice', row_id: 'white_teff', value: 'Grade 1' },
        { question_id: 'teff_photo', input_type: 'photo', row_id: 'white_teff', media_ref: 'media_001' },

        { question_id: 'teff_variety', input_type: 'single_choice', row_id: 'red_teff', value: 'Red' },
        { question_id: 'teff_available', input_type: 'single_choice', row_id: 'red_teff', value: 'Yes' },
        { question_id: 'teff_price', input_type: 'numeric_price', row_id: 'red_teff', value: 95, currency: 'ETB' },
        { question_id: 'teff_unit', input_type: 'single_choice', row_id: 'red_teff', value: 'kg' },
        { question_id: 'teff_quality_grade', input_type: 'single_choice', row_id: 'red_teff', value: 'Not mentioned' },
        { question_id: 'teff_photo', input_type: 'photo', row_id: 'red_teff', media_ref: 'media_002' },

        { question_id: 'teff_price_type', input_type: 'single_choice', value: 'Retail' },
        { question_id: 'teff_trader_note', input_type: 'text', value: 'Trader expects a price rise next week ahead of the holiday.' },

        // --- Red Onion module ---
        {
          question_id: 'onion_rows',
          input_type: 'repeat_table',
          value: [{ row_id: 'local_onion', pack_size: 'Local red onion', available: true }],
        },
        { question_id: 'onion_origin', input_type: 'single_choice', row_id: 'local_onion', value: 'Local red onion' },
        { question_id: 'onion_available', input_type: 'single_choice', row_id: 'local_onion', value: 'Yes' },
        { question_id: 'onion_price', input_type: 'numeric_price', row_id: 'local_onion', value: 72, currency: 'ETB' },
        { question_id: 'onion_unit', input_type: 'single_choice', row_id: 'local_onion', value: 'kg' },
        { question_id: 'onion_size_quality', input_type: 'single_choice', row_id: 'local_onion', value: 'Medium' },
        { question_id: 'onion_price_type', input_type: 'single_choice', value: 'Retail' },
        { question_id: 'onion_photo', input_type: 'photo', row_id: 'local_onion', media_ref: 'media_003' },

        // --- Edible Oil module: Forall 1L, Tena 3L ---
        {
          question_id: 'oil_rows',
          input_type: 'repeat_table',
          value: [
            { row_id: 'forall_1l', brand: 'Forall', pack_size: '1L', available: true },
            { row_id: 'tena_3l', brand: 'Tena', pack_size: '3L', available: false },
          ],
        },
        { question_id: 'oil_brand', input_type: 'single_choice', row_id: 'forall_1l', value: 'Forall' },
        { question_id: 'oil_pack_size', input_type: 'single_choice', row_id: 'forall_1l', value: '1L' },
        { question_id: 'oil_available', input_type: 'single_choice', row_id: 'forall_1l', value: 'Yes' },
        { question_id: 'oil_price', input_type: 'numeric_price', row_id: 'forall_1l', value: 410, currency: 'ETB' },
        { question_id: 'oil_stock_status', input_type: 'single_choice', row_id: 'forall_1l', value: 'Low' },
        { question_id: 'oil_price_photo', input_type: 'photo', row_id: 'forall_1l', media_ref: 'media_004' },

        { question_id: 'oil_brand', input_type: 'single_choice', row_id: 'tena_3l', value: 'Tena' },
        { question_id: 'oil_pack_size', input_type: 'single_choice', row_id: 'tena_3l', value: '3L' },
        { question_id: 'oil_available', input_type: 'single_choice', row_id: 'tena_3l', value: 'No' },
        { question_id: 'oil_stock_status', input_type: 'single_choice', row_id: 'tena_3l', value: 'Out of stock' },

        { question_id: 'oil_shelf_visibility', input_type: 'single_choice', value: 'Fair' },
        { question_id: 'oil_shelf_photo', input_type: 'photo', media_ref: 'media_005' },

        { question_id: 'confirm', input_type: 'checkbox', value: true },
      ],
      media: [
        // white_teff matches OUT-SHOLA-MARKET-002's "price_tag" baseline
        // exactly; the onion photo matches its "shelf" baseline exactly.
        // red_teff/oil use distinct real photos rather than reusing those
        // same bytes (which DuplicatePhotoRule would flag).
        { media_ref: 'media_001', question_id: 'teff_photo', row_id: 'white_teff', media_type: 'image', file_ref: 'seed-products/teff-flour-product.jpg', captured_at: '2026-07-24T08:34:00+03:00', gps_at_capture: { lat: 9.00999, lng: 38.79801 } },
        { media_ref: 'media_002', question_id: 'teff_photo', row_id: 'red_teff', media_type: 'image', file_ref: 'seed-products/teff-harvest-field.jpg', captured_at: '2026-07-24T08:36:10+03:00', gps_at_capture: { lat: 9.01003, lng: 38.79797 } },
        { media_ref: 'media_003', question_id: 'onion_photo', row_id: 'local_onion', media_type: 'image', file_ref: 'seed-products/red-onion-pile-market.jpg', captured_at: '2026-07-24T08:40:30+03:00', gps_at_capture: { lat: 9.01002, lng: 38.79799 } },
        { media_ref: 'media_004', question_id: 'oil_price_photo', row_id: 'forall_1l', media_type: 'image', file_ref: 'seed-products/oil-bottle-plain-3l.jpg', captured_at: '2026-07-24T08:45:00+03:00', gps_at_capture: { lat: 9.01000, lng: 38.79800 } },
        { media_ref: 'media_005', question_id: 'oil_shelf_photo', media_type: 'image', file_ref: 'seed-products/red-onion-store-display.jpg', captured_at: '2026-07-24T08:47:20+03:00', gps_at_capture: { lat: 9.01000, lng: 38.79800 } },
      ],
      prices: [
        { question_id: 'teff_price', sku_id: 'SKU-TEFF-WHITE-KG', row_id: 'white_teff', value: 105, currency: 'ETB', unit: 'per kg' },
        { question_id: 'teff_price', sku_id: 'SKU-TEFF-RED-KG', row_id: 'red_teff', value: 95, currency: 'ETB', unit: 'per kg' },
        { question_id: 'onion_price', sku_id: 'SKU-ONION-LOCAL-KG', row_id: 'local_onion', value: 72, currency: 'ETB', unit: 'per kg' },
        { question_id: 'oil_price', sku_id: 'SKU-OIL-FORALL-1L', row_id: 'forall_1l', value: 410, currency: 'ETB', unit: 'per bottle' },
      ],
      client_app_version: '2.5.0',
    },
  },

  {
    key: 'wholesale_scan_mercato',
    label: 'Complex — Wholesale/Market Price Scan (Mercato)',
    description: 'Bulk/wholesale price signal: edible oil by the carton, teff by the quintal, red onion by the sack. All three modules, wholesale price_type. Submission-level data is clean; same photo-pipeline caveat as the other complex samples, plus the informational "no duration baseline configured" flag.',
    envelope: {
      submission_id: 'b1a1c004-4444-4a44-9a44-000000000004',
      quest_id: 'QST-WHOLESALE-MERCATO-003',
      config_version: 'QST-WHOLESALE-MERCATO-003_v1',
      agent_id: 'AGT-30032',
      outlet_id: 'OUT-MERCATO-WHOLESALE-003',
      timestamps: {
        survey_start_at: '2026-07-23T09:00:00+03:00',
        survey_end_at: '2026-07-23T09:31:50+03:00',
        submitted_at: '2026-07-23T09:32:10+03:00',
      },
      gps: { lat: 9.03501, lng: 38.73999, accuracy_m: 5 },
      answers: [
        { question_id: 'branch_select', input_type: 'dropdown', value: 'OUT-MERCATO-WHOLESALE-003' },
        { question_id: 'confirm_branch', input_type: 'single_choice', value: 'Yes' },

        // --- Edible Oil module: Orkid 5L carton, Omar 3L carton ---
        {
          question_id: 'oil_rows',
          input_type: 'repeat_table',
          value: [
            { row_id: 'orkid_5l', brand: 'Orkid', pack_size: '5L', available: true },
            { row_id: 'omar_3l', brand: 'Omar', pack_size: '3L', available: true },
          ],
        },
        { question_id: 'oil_brand', input_type: 'single_choice', row_id: 'orkid_5l', value: 'Orkid' },
        { question_id: 'oil_pack_size', input_type: 'single_choice', row_id: 'orkid_5l', value: '5L' },
        { question_id: 'oil_available', input_type: 'single_choice', row_id: 'orkid_5l', value: 'Yes' },
        { question_id: 'oil_price', input_type: 'numeric_price', row_id: 'orkid_5l', value: 1870, currency: 'ETB' },
        { question_id: 'oil_stock_status', input_type: 'single_choice', row_id: 'orkid_5l', value: 'Normal' },
        { question_id: 'oil_price_photo', input_type: 'photo', row_id: 'orkid_5l', media_ref: 'media_001' },

        { question_id: 'oil_brand', input_type: 'single_choice', row_id: 'omar_3l', value: 'Omar' },
        { question_id: 'oil_pack_size', input_type: 'single_choice', row_id: 'omar_3l', value: '3L' },
        { question_id: 'oil_available', input_type: 'single_choice', row_id: 'omar_3l', value: 'Yes' },
        { question_id: 'oil_price', input_type: 'numeric_price', row_id: 'omar_3l', value: 1140, currency: 'ETB' },
        { question_id: 'oil_stock_status', input_type: 'single_choice', row_id: 'omar_3l', value: 'Normal' },
        { question_id: 'oil_price_photo', input_type: 'photo', row_id: 'omar_3l', media_ref: 'media_002' },

        { question_id: 'oil_shelf_visibility', input_type: 'single_choice', value: 'Good' },
        { question_id: 'oil_shelf_photo', input_type: 'photo', media_ref: 'media_003' },

        // --- Teff module: White (wholesale, quintal) ---
        {
          question_id: 'teff_rows',
          input_type: 'repeat_table',
          value: [{ row_id: 'white_teff_bulk', pack_size: 'White teff', available: true }],
        },
        { question_id: 'teff_variety', input_type: 'single_choice', row_id: 'white_teff_bulk', value: 'White' },
        { question_id: 'teff_available', input_type: 'single_choice', row_id: 'white_teff_bulk', value: 'Yes' },
        { question_id: 'teff_price', input_type: 'numeric_price', row_id: 'white_teff_bulk', value: 9200, currency: 'ETB' },
        { question_id: 'teff_unit', input_type: 'single_choice', row_id: 'white_teff_bulk', value: 'quintal' },
        { question_id: 'teff_quality_grade', input_type: 'single_choice', row_id: 'white_teff_bulk', value: 'Grade 1' },
        { question_id: 'teff_price_type', input_type: 'single_choice', value: 'Wholesale' },
        { question_id: 'teff_photo', input_type: 'photo', row_id: 'white_teff_bulk', media_ref: 'media_004' },

        // --- Red Onion module: Local, sack ---
        {
          question_id: 'onion_rows',
          input_type: 'repeat_table',
          value: [{ row_id: 'local_onion_sack', pack_size: 'Local red onion', available: true }],
        },
        { question_id: 'onion_origin', input_type: 'single_choice', row_id: 'local_onion_sack', value: 'Local red onion' },
        { question_id: 'onion_available', input_type: 'single_choice', row_id: 'local_onion_sack', value: 'Yes' },
        { question_id: 'onion_price', input_type: 'numeric_price', row_id: 'local_onion_sack', value: 4200, currency: 'ETB' },
        { question_id: 'onion_unit', input_type: 'single_choice', row_id: 'local_onion_sack', value: 'sack' },
        { question_id: 'onion_size_quality', input_type: 'single_choice', row_id: 'local_onion_sack', value: 'Mixed' },
        { question_id: 'onion_price_type', input_type: 'single_choice', value: 'Wholesale' },
        { question_id: 'onion_photo', input_type: 'photo', row_id: 'local_onion_sack', media_ref: 'media_005' },
        { question_id: 'onion_trader_note', input_type: 'text', value: 'Supply is steady this week; price flat vs. last visit.' },

        { question_id: 'confirm', input_type: 'checkbox', value: true },
      ],
      media: [
        // orkid_5l and the onion sack photo each match one of
        // OUT-MERCATO-WHOLESALE-003's two baselines exactly (same file +
        // GPS point); omar_3l/shelf/teff use distinct real photos rather
        // than reusing bytes already used elsewhere in this submission.
        { media_ref: 'media_001', question_id: 'oil_price_photo', row_id: 'orkid_5l', media_type: 'image', file_ref: 'seed-products/oil-bottle-sunflower-1l.jpg', captured_at: '2026-07-23T09:05:00+03:00', gps_at_capture: { lat: 9.03499, lng: 38.74002 } },
        { media_ref: 'media_002', question_id: 'oil_price_photo', row_id: 'omar_3l', media_type: 'image', file_ref: 'seed-products/oil-bottle-5l.jpg', captured_at: '2026-07-23T09:07:40+03:00', gps_at_capture: { lat: 9.03497, lng: 38.74003 } },
        { media_ref: 'media_003', question_id: 'oil_shelf_photo', media_type: 'image', file_ref: 'seed-products/supermarket-shelf.jpg', captured_at: '2026-07-23T09:09:00+03:00', gps_at_capture: { lat: 9.03500, lng: 38.73999 } },
        { media_ref: 'media_004', question_id: 'teff_photo', row_id: 'white_teff_bulk', media_type: 'image', file_ref: 'seed-products/teff-flour-product.jpg', captured_at: '2026-07-23T09:16:00+03:00', gps_at_capture: { lat: 9.03502, lng: 38.74000 } },
        { media_ref: 'media_005', question_id: 'onion_photo', row_id: 'local_onion_sack', media_type: 'image', file_ref: 'seed-products/red-onion-store-display.jpg', captured_at: '2026-07-23T09:24:00+03:00', gps_at_capture: { lat: 9.03502, lng: 38.73998 } },
      ],
      prices: [
        { question_id: 'oil_price', sku_id: 'SKU-OIL-ORKID-5L', row_id: 'orkid_5l', value: 1870, currency: 'ETB', unit: 'per carton' },
        { question_id: 'oil_price', sku_id: 'SKU-OIL-OMAR-3L', row_id: 'omar_3l', value: 1140, currency: 'ETB', unit: 'per carton' },
        { question_id: 'teff_price', sku_id: 'SKU-TEFF-WHITE-QUINTAL', row_id: 'white_teff_bulk', value: 9200, currency: 'ETB', unit: 'per quintal' },
        { question_id: 'onion_price', sku_id: 'SKU-ONION-LOCAL-SACK', row_id: 'local_onion_sack', value: 4200, currency: 'ETB', unit: 'per sack' },
      ],
      client_app_version: '2.5.0',
    },
  },

  {
    key: 'flagged_mixed_retail_saris',
    label: 'Deliberately Flagged — Mixed Retail Scan (Saris/Gotera)',
    description: 'Oil + beer + red onion with a price outlier, a stale/reused onion photo, GPS drift, and an implausibly fast survey. Expect several flags.',
    envelope: {
      submission_id: 'b1a1c005-5555-4a55-9a55-000000000005',
      quest_id: 'QST-MIXEDRETAIL-SARIS-004',
      config_version: 'QST-MIXEDRETAIL-SARIS-004_v1',
      agent_id: 'AGT-30099',
      outlet_id: 'OUT-SARIS-GROCERY-004',
      timestamps: {
        survey_start_at: '2026-07-24T13:40:00+03:00',
        survey_end_at: '2026-07-24T13:40:14+03:00',
        submitted_at: '2026-07-24T13:40:21+03:00',
      },
      // ~230m off the outlet's registered point — well outside the
      // outlet's own gps_radius_m (150m), inside the wider quest geofence.
      gps: { lat: 8.95710, lng: 38.76650, accuracy_m: 14 },
      answers: [
        { question_id: 'branch_select', input_type: 'dropdown', value: 'OUT-SARIS-GROCERY-004' },
        { question_id: 'confirm_branch', input_type: 'single_choice', value: 'Yes' },

        {
          question_id: 'oil_rows',
          input_type: 'repeat_table',
          value: [{ row_id: 'tena_1l', brand: 'Tena', pack_size: '1L', available: true }],
        },
        { question_id: 'oil_brand', input_type: 'single_choice', row_id: 'tena_1l', value: 'Tena' },
        { question_id: 'oil_pack_size', input_type: 'single_choice', row_id: 'tena_1l', value: '1L' },
        { question_id: 'oil_available', input_type: 'single_choice', row_id: 'tena_1l', value: 'Yes' },
        // ~10x the going rate seen in the other samples — deliberate
        // price_range_outlier bait (payload-supplied market_range below).
        { question_id: 'oil_price', input_type: 'numeric_price', row_id: 'tena_1l', value: 4300, currency: 'ETB' },
        { question_id: 'oil_stock_status', input_type: 'single_choice', row_id: 'tena_1l', value: 'Normal' },
        { question_id: 'oil_price_photo', input_type: 'photo', row_id: 'tena_1l', media_ref: 'media_001' },
        { question_id: 'oil_shelf_visibility', input_type: 'single_choice', value: 'Poor' },
        { question_id: 'oil_shelf_photo', input_type: 'photo', media_ref: 'media_002' },

        {
          question_id: 'beer_rows',
          input_type: 'repeat_table',
          value: [{ row_id: 'dashen', brand: 'Dashen', pack_size: '330ml bottle', available: true }],
        },
        { question_id: 'beer_brand', input_type: 'single_choice', row_id: 'dashen', value: 'Dashen' },
        { question_id: 'beer_sku', input_type: 'text', row_id: 'dashen', value: '330ml bottle' },
        { question_id: 'beer_available', input_type: 'single_choice', row_id: 'dashen', value: 'Yes' },
        { question_id: 'beer_price', input_type: 'numeric_price', row_id: 'dashen', value: 60, currency: 'ETB' },
        { question_id: 'beer_cold_availability', input_type: 'single_choice', row_id: 'dashen', value: 'Not cold' },
        { question_id: 'beer_visibility', input_type: 'single_choice', row_id: 'dashen', value: 'Shelf visible' },
        { question_id: 'beer_photo', input_type: 'photo', row_id: 'dashen', media_ref: 'media_003' },
        { question_id: 'beer_photo_not_allowed', input_type: 'single_choice', value: 'No' },

        {
          question_id: 'onion_rows',
          input_type: 'repeat_table',
          value: [{ row_id: 'unknown_onion', pack_size: 'Unknown origin red onion', available: true }],
        },
        { question_id: 'onion_origin', input_type: 'single_choice', row_id: 'unknown_onion', value: 'Unknown origin' },
        { question_id: 'onion_available', input_type: 'single_choice', row_id: 'unknown_onion', value: 'Yes' },
        { question_id: 'onion_price', input_type: 'numeric_price', row_id: 'unknown_onion', value: 78, currency: 'ETB' },
        { question_id: 'onion_unit', input_type: 'single_choice', row_id: 'unknown_onion', value: 'kg' },
        { question_id: 'onion_size_quality', input_type: 'single_choice', row_id: 'unknown_onion', value: 'Small' },
        { question_id: 'onion_price_type', input_type: 'single_choice', value: 'Retail' },
        // captured 6 days before this submission — TimestampConsistencyRule's
        // stale_photo check (looks like a reused/gallery photo, not a live capture).
        { question_id: 'onion_photo', input_type: 'photo', row_id: 'unknown_onion', media_ref: 'media_004' },
        { question_id: 'onion_trader_note', input_type: 'text', value: '' },

        { question_id: 'confirm', input_type: 'checkbox', value: true },
      ],
      media: [
        { media_ref: 'media_001', question_id: 'oil_price_photo', row_id: 'tena_1l', media_type: 'image', file_ref: 'seed-products/oil-bottle-sunflower-1l.jpg', captured_at: '2026-07-24T13:40:05+03:00', gps_at_capture: { lat: 8.95711, lng: 38.76649 } },
        { media_ref: 'media_002', question_id: 'oil_shelf_photo', media_type: 'image', file_ref: 'seed-products/supermarket-shelf.jpg', captured_at: '2026-07-24T13:40:07+03:00', gps_at_capture: { lat: 8.9571, lng: 38.7665 } },
        { media_ref: 'media_003', question_id: 'beer_photo', row_id: 'dashen', media_type: 'image', file_ref: 'seed-products/beer-bottles-shelf.jpg', captured_at: '2026-07-24T13:40:09+03:00', gps_at_capture: { lat: 8.95709, lng: 38.76651 } },
        { media_ref: 'media_004', question_id: 'onion_photo', row_id: 'unknown_onion', media_type: 'image', file_ref: 'seed-products/red-onion-pile-market.jpg', captured_at: '2026-07-18T09:00:00+03:00', gps_at_capture: { lat: 8.9605, lng: 38.7720 } },
      ],
      prices: [
        {
          question_id: 'oil_price', sku_id: 'SKU-OIL-TENA-1L', row_id: 'tena_1l', value: 4300, currency: 'ETB', unit: 'per bottle',
          market_range_price: '400-460',
        },
        { question_id: 'beer_price', sku_id: 'SKU-BEER-DASHEN-330ML', row_id: 'dashen', value: 60, currency: 'ETB', unit: 'per bottle' },
        { question_id: 'onion_price', sku_id: 'SKU-ONION-UNKNOWN-KG', row_id: 'unknown_onion', value: 78, currency: 'ETB', unit: 'per kg' },
      ],
      client_app_version: '2.5.0',
    },
  },
];
