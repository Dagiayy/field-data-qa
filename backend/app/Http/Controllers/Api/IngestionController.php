<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngestSubmissionRequest;
use App\Services\Ingestion\SubmissionIngestionService;

class IngestionController extends Controller
{
    public function __construct(private SubmissionIngestionService $ingestionService)
    {
    }

    public function store(IngestSubmissionRequest $request)
    {
        $submission = $this->ingestionService->ingest($request->validated());

        return response()->json([
            'success' => true,
            'submission_id' => $submission->id,
            'status' => $submission->status->toApiValue(),
            'flags_raised' => $submission->qaFlags->count(),
        ], 201);
    }
}
