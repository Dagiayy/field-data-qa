# Metrix QA Ingestion — Test Payloads

Six files, meant to be used together. These match the **Metrix Beta Quest
Package** brief (product groups: edible oil, teff, red onion, beer, across
six non-clustered Addis Ababa locations) and the data seeded by
`backend/database/seeders/OutletSeeder.php` / `QuestSeeder.php`.

- `outlet_and_baseline_reference_data.json` — **seed this first** (or just run
  `php artisan migrate:fresh --seed`, which produces exactly this data). The
  Outlet + OutletBaseline data that must already exist before you send any
  submission through, since the QA engine looks baselines up by `outlet_id`.
  It also includes a `how_each_payload_should_resolve` section telling you
  exactly what each test payload should trigger, dimension by dimension.
- `payload_1_beer_scan_kazanchis.json` — the simplest shape: one brand, one
  module, one photo deliberately matched to the outlet's own registered
  baseline. Expect only the informational `no_duration_baseline_configured`
  flag (Quest Baseline Management — see Baseline Management → "By Quest" in
  the dashboard — hasn't been configured for this quest yet).
- `payload_2_supermarket_scan_bole.json`, `payload_3_openmarket_scan_shola.json`,
  `payload_4_wholesale_scan_mercato.json` — "complex" multi-module,
  multi-brand/variety submissions (repeat-table rows, several photos, several
  prices each). Submission-level data (price, GPS, timing) is clean in all
  three; because these use real photos through the real QA image pipeline
  (not synthetic test patterns), expect some genuine photo-quality/
  baseline-similarity flags on the rows not pinned to a registered baseline
  photo — that's the pipeline doing its job on real content, not a bug.
- `payload_5_flagged_mixed_retail_saris.json` — deliberately broken in four
  ways at once (GPS drift past both the outlet radius and the baseline
  tolerance, a price outlier, an impossibly fast completion time, and a
  stale/reused photo). Use this to confirm the automated rule engine actually
  catches all of these and produces the right `QAFlag` rows rather than
  silently passing.

These also load as one-click starters in the QA Dashboard's manual ingestion
tester (`frontend/src/pages/TestIngestion.tsx`, backed by
`frontend/src/api/samplePayloads.ts`) — the JSON here and the payloads in that
file are the same data.

## What changed from the earlier envelope example

- **No `Trust_Score` field in the payload.** Trust score can't be computed by
  the Mini App at submission time — it depends on the quest's admin-configured
  dimension weights and on QA results that don't exist yet when the agent hits
  submit. It's computed server-side, *after* automated + manual QA finish, and
  stored as its own record tied to the submission and the agent. Nothing to
  send here.
- **Only one GPS pair travels per photo, and that's already correct.** The
  "shop's actual location" and "exact location when the photo was taken" are
  two different things, but only one of them belongs in the submission: the
  photo's location (`media[].gps_at_capture`). The shop's location is already
  known to the system (`Outlet.gps_lat/lng` and, more precisely,
  `OutletBaseline.baseline_gps_lat/lng` for a specific shelf/spot) — that's why
  it's in the reference file, not repeated in every submission.

## About the image files

`file_ref` / `baseline_photo_path` values are relative paths under
`backend/storage/app/public/seed-products/` — real product/shelf photos
(sourced from Wikimedia Commons, downloaded once into local storage rather
than hotlinked). Hotlinking an external CDN doesn't work reliably for
something the QA rule engine's `PhotoAnalysisService`/`ImageEmbeddingService`/
`ImageSimilarityService` each fetch server-side on *every* submission —
Wikimedia's own servers 403 requests with no descriptive User-Agent and
rate-limit repeated fetches from the same source. Local storage has neither
problem, and matches the project's local-first design principle (see the
root `CLAUDE.md`). All are above `PhotoResolutionRule`'s 1280×720 minimum —
see `backend/app/Services/Qa/Rules/PhotoResolutionRule.php`.

Using a relative storage path (not a full URL) as `file_ref` also matches
what the real Mini App pipeline will eventually send once Phase 2's
multipart upload contract lands (see the root `CLAUDE.md`) — the backend
resolves both the same way (`App\Support\QaPresenter::resolveUrl`).

One deliberate detail: in payload 5, the red onion photo was captured 6 days
before `submitted_at` — this simulates a stale/gallery-reused photo for
testing `TimestampConsistencyRule`'s `stale_photo` check.

## Suggested test order

1. Run `php artisan migrate:fresh --seed` (or load
   `outlet_and_baseline_reference_data.json` into your Outlet/OutletBaseline
   tables via the baseline API).
2. POST payload 1 → expect `status: pending`, only the informational
   `no_duration_baseline_configured` flag.
3. POST payloads 2–4 → expect `status: pending`, clean submission-level data,
   possible real photo-quality/baseline-similarity flags (see each payload's
   description above).
4. POST payload 5 → expect `status: pending` with several `QAFlag` rows (see
   the reference file's notes for exactly which ones), ready to test a manual
   Reject decision with a reason code.
