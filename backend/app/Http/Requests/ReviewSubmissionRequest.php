<?php

namespace App\Http\Requests;

use App\Enums\QaReviewDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(QaReviewDecision::class)],
            'reason_code' => ['required_if:decision,reject', 'nullable', 'string', 'exists:rejection_reasons,code'],
            'note' => ['nullable', 'string'],
            'backcheck_outcome' => ['nullable', 'string', 'in:confirmed,not_confirmed'],
        ];
    }
}
