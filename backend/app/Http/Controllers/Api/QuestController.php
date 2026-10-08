<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quest;

/**
 * Flat quest list — backs the quest picker in Baseline Management
 * (frontend/src/pages/BaselineManagement.tsx). Mirrors RejectionReasonController's
 * unpaginated shape; the quest table is small enough that pagination isn't
 * worth the complexity yet.
 */
class QuestController extends Controller
{
    public function index()
    {
        $quests = Quest::query()->with('outlet')->orderBy('title')->get();

        return response()->json([
            'data' => $quests->map(fn (Quest $quest) => [
                'id' => $quest->form_code,
                'title' => $quest->title,
                'form_code' => $quest->form_code,
                'quest_type' => $quest->quest_type->value,
                'active' => $quest->active,
                'has_duration_baseline' => $quest->hasDurationBaseline(),
                'outlet_id' => $quest->outlet?->code,
                'outlet_name' => $quest->outlet?->name,
            ])->all(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'total' => $quests->count(),
            ],
        ]);
    }
}
