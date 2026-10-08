<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Models\RejectionReason;

class RejectionReasonController extends Controller
{
    public function index()
    {
        $reasons = RejectionReason::query()->where('active', true)->orderBy('label')->get();

        return response()->json([
            'data' => $reasons->map(fn (RejectionReason $reason) => [
                'code' => $reason->code,
                'label' => $reason->label,
                'applies_to_quest_types' => $reason->applies_to_quest_types,
            ])->all(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'total' => $reasons->count(),
            ],
        ]);
    }
}
