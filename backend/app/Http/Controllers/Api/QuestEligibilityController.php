<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Quest;
use App\Services\Geo\Haversine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Checkpoint 1 of GPS validation: before the Mini App shows a quest as
 * available or lets an agent accept it, the agent's current device GPS must
 * be within the shop's registered radius. This is a pre-submission gate the
 * existing Mini App calls — it never touches submission data.
 */
class QuestEligibilityController extends Controller
{
    public function check(Request $request, string $quest)
    {
        $data = $request->validate([
            'outlet_id' => ['required', 'string'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
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

        $radiusM = $outlet->gps_radius_m;
        $distanceM = Haversine::distanceMeters(
            (float) $data['lat'],
            (float) $data['lng'],
            (float) $outlet->gps_lat,
            (float) $outlet->gps_lng,
        );

        return response()->json([
            'quest_id' => $questModel->form_code,
            'outlet_id' => $outlet->code,
            'eligible' => $distanceM <= $radiusM,
            'distance_m' => round($distanceM, 1),
            'radius_m' => $radiusM,
        ]);
    }
}
