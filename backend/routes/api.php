<?php

use App\Http\Controllers\Api\IngestionController;
use App\Http\Controllers\Api\OutletBaselineController;
use App\Http\Controllers\Api\OutletController;
use App\Http\Controllers\Api\QuestAcceptanceController;
use App\Http\Controllers\Api\QuestBaselineController;
use App\Http\Controllers\Api\QuestController;
use App\Http\Controllers\Api\QuestEligibilityController;
use App\Http\Controllers\Qa\AgentAnswerPatternController;
use App\Http\Controllers\Qa\AgentListController;
use App\Http\Controllers\Qa\AgentRejectionFeedbackController;
use App\Http\Controllers\Qa\AgentTrustScoreController;
use App\Http\Controllers\Qa\QueueController;
use App\Http\Controllers\Qa\RejectionReasonController;
use App\Http\Controllers\Qa\ReportsController;
use App\Http\Controllers\Qa\SubmissionController;
use App\Http\Controllers\Qa\ImageTestController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'metrix-backend',
    ]);
});

// The Mini App pipeline's only integration point — shared-secret auth, not a
// dashboard user session. See App\Http\Middleware\VerifyIngestionKey.
Route::post('/ingest', [IngestionController::class, 'store'])->middleware('ingestion.key');

// No login required — the QA dashboard talks to this API directly, with no
// auth gate in front of it.
Route::get('/outlets', [OutletController::class, 'index']);

// Baseline Management (frontend/src/pages/BaselineManagement.tsx) — GPS +
// reference-photo spots physically belong to the outlet, but the page
// itself is quest-first: it resolves a quest's outlet via Quest::outlet_id
// (see QuestBaselineController) and calls these using that outlet's code.
Route::get('/outlets/{outlet}/baselines', [OutletBaselineController::class, 'index']);
Route::post('/outlets/{outlet}/baselines', [OutletBaselineController::class, 'store']);
Route::put('/outlets/{outlet}/baselines/{baseline}', [OutletBaselineController::class, 'update']);

Route::get('/quests', [QuestController::class, 'index']);
// Quest-level Baseline Management: expected survey duration range +
// expected price range per SKU, looked up by quest_id for every future
// submission to that quest (SurveyDurationRule, MarketPriceRangeResolver).
Route::get('/quests/{quest}/baseline', [QuestBaselineController::class, 'show']);
Route::put('/quests/{quest}/baseline', [QuestBaselineController::class, 'update']);

// Agent Trust Score dashboard page (frontend/src/pages/AgentTrustScore.tsx)
// — dashboard-only, matches that page's existing (previously mock-only) API
// call shape exactly, so no frontend URL change is needed.
Route::get('/agents', [AgentListController::class, 'index']);
Route::get('/agents/{agentId}/trust-score', [AgentTrustScoreController::class, 'show']);

// Checkpoint 1 of GPS validation (quest visibility/acceptance) — the
// existing Mini App calls this before showing a quest as available or
// letting an agent accept it.
Route::get('/quests/{quest}/eligibility', [QuestEligibilityController::class, 'check']);

// Time Validation: records the start of the accept-to-start interval.
Route::post('/quests/{quest}/accept', [QuestAcceptanceController::class, 'store']);

Route::prefix('qa')->group(function () {
    Route::get('/queue', [QueueController::class, 'index']);
    // QA Lead Dashboard (frontend/src/pages/QAOverview.tsx) — live aggregate
    // metrics, not a precomputed/cached snapshot.
    Route::get('/reports/overview', [ReportsController::class, 'overview']);
    Route::post('/image-test', [ImageTestController::class, 'analyze']);
    // Manual payload tester (frontend/src/pages/TestIngestion.tsx) — for
    // exercising the ingestion pipeline before the real Mini App pipeline
    // is live. Deliberately points at the exact same IngestionController
    // action as the real /api/ingest endpoint (same validation, same
    // SubmissionIngestionService, same QA engine run) so a payload that
    // passes here behaves identically once the real integration lands —
    // it just skips the shared-secret key since the QA dashboard is
    // already a trusted caller (see App\Http\Middleware\VerifyIngestionKey).
    Route::post('/test-ingest', [IngestionController::class, 'store']);
    Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);
    Route::get('/rejection-reasons', [RejectionReasonController::class, 'index']);
    Route::post('/submissions/{submission}/review', [SubmissionController::class, 'review']);
    Route::get('/agents/{agentId}/answer-patterns', [AgentAnswerPatternController::class, 'show']);
    Route::get('/agents/{agentId}/rejection-feedback', [AgentRejectionFeedbackController::class, 'show']);
});
