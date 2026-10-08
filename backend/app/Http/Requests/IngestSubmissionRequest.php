<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the pre-agreed ingestion envelope shape (project CLAUDE.md) at
 * the boundary only — question/answer contents stay flexible per quest type,
 * so nothing about answers[].value is constrained beyond "it exists".
 */
class IngestSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_id' => ['required', 'string'],
            'quest_id' => ['required', 'string'],
            'config_version' => ['required', 'string'],
            'agent_id' => ['required', 'string'],
            'outlet_id' => ['required', 'string'],

            'timestamps' => ['required', 'array'],
            'timestamps.survey_start_at' => ['required', 'date'],
            'timestamps.survey_end_at' => ['required', 'date'],
            'timestamps.submitted_at' => ['required', 'date'],

            'gps' => ['required', 'array'],
            'gps.lat' => ['required', 'numeric', 'between:-90,90'],
            'gps.lng' => ['required', 'numeric', 'between:-180,180'],
            'gps.accuracy_m' => ['nullable', 'numeric'],

            'answers' => ['present', 'array'],
            'answers.*.question_id' => ['required', 'string'],
            'answers.*.input_type' => ['required', 'string'],
            'answers.*.row_id' => ['nullable', 'string'],
            'answers.*.media_ref' => ['nullable', 'string'],
            // No type constraint on purpose (flexible per quest type) — but
            // it still needs *a* rule, or FormRequest::validated() silently
            // drops the key entirely rather than passing it through.
            'answers.*.value' => ['nullable'],

            'media' => ['present', 'array'],
            'media.*.media_ref' => ['required', 'string'],
            'media.*.question_id' => ['required', 'string'],
            'media.*.row_id' => ['nullable', 'string'],
            'media.*.media_type' => ['required', 'string'],
            'media.*.file_ref' => ['required', 'string'],
            'media.*.captured_at' => ['required', 'date'],
            'media.*.gps_at_capture' => ['required', 'array'],
            'media.*.gps_at_capture.lat' => ['required', 'numeric', 'between:-90,90'],
            'media.*.gps_at_capture.lng' => ['required', 'numeric', 'between:-180,180'],

            'prices' => ['present', 'array'],
            'prices.*.question_id' => ['required', 'string'],
            'prices.*.sku_id' => ['required', 'string'],
            'prices.*.row_id' => ['nullable', 'string'],
            'prices.*.value' => ['required', 'numeric'],
            'prices.*.currency' => ['required', 'string'],
            'prices.*.unit' => ['nullable', 'string'],
            // "MarketRangePrice" — a range like "350-450" or a single
            // expected value like "410". Optional: if the client doesn't
            // send one, that price simply has no expected-range check.
            'prices.*.market_range_price' => ['nullable', 'string'],

            'client_app_version' => ['nullable', 'string'],
        ];
    }
}
