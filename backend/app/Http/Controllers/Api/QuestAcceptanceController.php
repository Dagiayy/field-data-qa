<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Quest;
use App\Models\QuestAcceptance;
use App\Services\Geo\Haversine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Records the moment an agent accepts a quest at a specific shop — the
 * Mini App calls this right after Checkpoint 1 (quest eligibility, see
 * QuestEligibilityController) passes. This is what "quest accepted" in the
 * Time Validation spec refers to: the start of the accept-to-start
 * interval, captured independently of whether a submission ever follows.
 */
class QuestAcceptanceController extends Controller
{
    public function store(Request $request, string $quest)
    {
        $data = $request->validate([
            'outlet_id' => ['required', 'string'],
            'agent_id' => ['required', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $questModel = Quest::where('form_code', $quest)->first();
        if (! $questModel) {
            throw ValidationException::withMessages([
                'quest' => "No quest is registered with form_code [{$quest}].",
            ]);
        }

        $outlet = Outlet::where('code', $data['outlet_id'])->first();
        if (! $outlet) {
            throw ValidationException::withMessages([
                'outlet_id' => "No outlet is registered with code [{$data['outlet_id']}].",
            ]);
        }

        if (isset($data['lat'], $data['lng'])) {
            $distanceM = Haversine::distanceMeters(
                (float) $data['lat'],
                (float) $data['lng'],
                (float) $outlet->gps_lat,
                (float) $outlet->gps_lng,
            );

            if ($distanceM > $outlet->gps_radius_m) {
                throw ValidationException::withMessages([
                    'location' => "Agent is {$distanceM}m from the shop, outside its {$outlet->gps_radius_m}m radius — quest cannot be accepted from here.",
                ]);
            }
        }

        $acceptance = QuestAcceptance::create([
            'quest_id' => $questModel->id,
            'outlet_id' => $outlet->id,
            'agent_id' => $data['agent_id'],
            'accepted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'quest_id' => $questModel->form_code,
            'outlet_id' => $outlet->code,
            'agent_id' => $acceptance->agent_id,
            'accepted_at' => $acceptance->accepted_at->toIso8601String(),
        ], 201);
    }
}
