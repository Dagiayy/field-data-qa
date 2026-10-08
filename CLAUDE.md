Project: Metrix Quality Assurance & Payment Approval System

Background: Metrix is a Telegram Mini App + Admin dashboard (already built and
running) for field data collection — agents complete "quests" (FMCG price/shelf
checks, agriculture/development verification forms, etc.) and submit data via API.
Quest structure is configurable and varies in volume and shape from one quest type
to another (some are 5-question price checks, others are 30-question repeat-table
verification forms).

What we are building now: the system that sits between "a submission arrives via
API" and "a client/manager can trust the data and an agent gets paid." This is:
1. An ingestion API that accepts submissions in a generic envelope (see schema below).
2. An automated QA rule engine (Layer 1) that runs on every submission.
3. A manual QA review workflow (Layer 2) for a human reviewer.
4. A payment-status system that is strictly derived from QA decisions.
5. A QA Dashboard (web frontend) for reviewers and the QA Lead.

IMPORTANT — scope and deployment philosophy for this build:
- The ingestion API is the only thing the existing Mini App pipeline talks to.
  Nothing else needs to be internet-facing right now.
- Everything downstream of ingestion — file storage, the QA rule engine, the image
  comparison logic, the payment-status derivation, and the dashboard — runs
  entirely locally (same machine/server, or a local network), with no paid or
  cloud-hosted services. No AWS S3, no hosted embeddings API, no managed queue
  service. Use free, open-source, self-hostable tools throughout.
- Keep every integration point abstracted (storage disk, queue driver, image model)
  so that later, if we do want to move a piece to the cloud, it's a config change,
  not a rewrite. Laravel's own abstractions (Storage facade, Queue facade) already
  give us this for free — use them rather than hardcoding local-only assumptions
  into business logic.

Non-negotiable design rules:
- Payment status must ONLY ever be derived from QA status. Never set independently.
- Every QA decision must be append-only / audit-logged. Never overwrite history.
- The system must handle different quest shapes without schema changes per quest
  type — answers are stored as flexible {question_id, value} pairs, not fixed columns.
- Photos and prices are mirrored out of the raw answers into their own arrays
  (media[], prices[]) for fast access by the QA engine and dashboard.
- Every dashboard/export number must be traceable back to: exact submission, photo,
  GPS point, timestamp, agent, and QA decision.

Tech stack (Laravel + React, local-first, free/open-source throughout):
- Backend: Laravel (latest stable), PHP 8.3+, PostgreSQL as the database. This is
  a pure API backend (routes/api.php) — it does not render any HTML pages itself.
- Storage: Laravel's local filesystem disk (`storage/app/public`, served via the
  standard `php artisan storage:link` symlink) — not S3, not MinIO, just plain
  local disk. Use Laravel's `Storage` facade throughout so a future move to a
  cloud disk driver is a one-line config change, not a code change.
- Queue: Laravel's built-in queue system with the `database` queue driver (jobs
  table in Postgres) — no Redis, no external broker needed. This keeps the whole
  stack to "PHP + Postgres + one small Python helper," nothing else to run or pay for.
- Auth: Laravel Sanctum in API-token mode. The frontend is a fully separate SPA
  (not a first-party Laravel-served app), so this is a Bearer-token flow — the
  frontend logs in via an API call, receives a personal access token, and sends
  it as a Bearer token on every subsequent request. Configure Laravel's CORS
  (`config/cors.php`) to allow the frontend's origin. Roles/permissions via the
  `spatie/laravel-permission` package (qa_reviewer, qa_lead, field_ops,
  read_only, admin). Do not build agent-facing Mini App auth — that already exists.
- Frontend: a standalone React + TypeScript + Vite + Tailwind CSS single-page app,
  its own separate service/codebase, talking to the Laravel backend purely
  through the REST API defined in this document (JSON in/out, Bearer token in the
  Authorization header). Not Inertia, not server-rendered, not Livewire — a
  normal decoupled SPA that could point at any backend speaking this API.
- Image similarity: a small local Python microservice (FastAPI) using `imagehash`
  for perceptual-hash comparison and a locally-run open-source vision embedding
  model (e.g. `open_clip`, running on CPU is fine at this scale) for location/angle
  similarity. This runs on the same machine, called over localhost HTTP — no
  external API, no API key, no cost.
- Deployment target for now: everything runs via Docker Compose on one machine/
  server (postgres, backend, frontend, image-service). No cloud services required
  to run the full system end to end.

The full incoming submission envelope (already agreed, do not redesign it —
implement exactly this):

{
  "submission_id": "uuid",              // idempotency key
  "quest_id": "string",
  "config_version": "string",           // which version of the quest config was live
  "agent_id": "string",
  "outlet_id": "string",                // links to Outlet/Entity record incl. baseline
  "timestamps": {
    "survey_start_at": "ISO8601",
    "survey_end_at": "ISO8601",
    "submitted_at": "ISO8601"
  },
  "gps": { "lat": number, "lng": number, "accuracy_m": number },
  "answers": [
    { "question_id": "string", "input_type": "string", "value": any, "row_id"?: "string" }
    // input_type examples: dropdown, single_choice, multiple_choice, numeric_price,
    // photo, repeat_table, text, checkbox
    // photo answers carry "media_ref" instead of "value"
  ],
  "media": [
    {
      "media_ref": "string", "question_id": "string", "row_id"?: "string",
      "media_type": "image", "file_ref": "string (local storage path)",
      "captured_at": "ISO8601",
      "gps_at_capture": { "lat": number, "lng": number }
    }
  ],
  "prices": [
    { "question_id": "string", "sku_id": "string", "row_id"?: "string",
      "value": number, "currency": "string", "unit"?: "string" }
  ],
  "client_app_version": "string"
}

Note: for media[], the Mini App/existing pipeline should upload the photo bytes to
our ingestion API as a file upload (multipart) alongside the JSON envelope, and we
store it to local disk ourselves and record the resulting local file_ref — do not
expect the incoming payload to already contain a pre-hosted URL, since we are not
using any external storage. If the existing pipeline currently uploads photos
somewhere else first, flag this so we can agree on the exact multipart contract in
Phase 2.

Baseline validation requirement (added in this build):
- Each Outlet/Entity has one or more registered BASELINE PHOTOS (a reference photo
  of the exact shelf/shop spot, taken once during setup, stored locally) and a
  BASELINE GPS POINT (precise manual GPS reading of that exact spot, taken once
  during setup). These are NOT sent with every submission — they are looked up
  server-side by outlet_id (and spot_label where an outlet has more than one
  designated photo spot, e.g. "shelf" vs "entrance" vs "cooler"). See Phase 2 and
  Phase 4 below for schema and logic.
- Every incoming submission photo gets compared against the relevant baseline photo
  for that outlet + spot (via the local image-service), and every incoming photo's
  gps_at_capture gets compared against that outlet's baseline GPS point (tight
  radius), in addition to the wider quest-level geofence check against the quest's
  designated area (up to 5 km, configurable per quest).