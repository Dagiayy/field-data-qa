<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\OutletBaseline;
use App\Services\Qa\QaReevaluationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for OutletBaseline — the GPS point + reference photo registered once
 * per outlet+spot (see project CLAUDE.md "Baseline validation requirement").
 * Backs frontend/src/pages/BaselineManagement.tsx, which was already built
 * against this exact contract and previously fell back to mock data because
 * these routes didn't exist yet.
 */
class OutletBaselineController extends Controller
{
    public function __construct(private QaReevaluationService $qaReevaluation)
    {
    }

    public function index(Request $request, string $outlet)
    {
        $outletModel = $this->findOutlet($outlet);

        $baselines = $outletModel->baselines()->orderBy('spot_label')->get();

        return response()->json([
            'data' => $baselines->map(fn (OutletBaseline $b) => $this->present($b))->all(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'total' => $baselines->count(),
            ],
        ]);
    }

    public function store(Request $request, string $outlet)
    {
        $outletModel = $this->findOutlet($outlet);

        $data = $request->validate([
            'spot_label' => ['required', 'string', 'max:255'],
            'baseline_gps_lat' => ['required', 'numeric', 'between:-90,90'],
            'baseline_gps_lng' => ['required', 'numeric', 'between:-180,180'],
            'baseline_gps_radius_m' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'photo' => ['required', 'image', 'max:10240'],
        ]);

        // outlet_baselines has a unique(outlet_id, spot_label) constraint —
        // caught here with a friendly message instead of letting a raw
        // QueryException surface as a 500 (the dropdown of spot labels in
        // the frontend form makes a same-outlet collision a realistic case,
        // e.g. registering "Other Spot" twice).
        if ($outletModel->baselines()->where('spot_label', $data['spot_label'])->exists()) {
            throw ValidationException::withMessages([
                'spot_label' => "This outlet already has a baseline registered for spot [{$data['spot_label']}] — edit that one instead of creating a duplicate.",
            ]);
        }

        $photoPath = $request->file('photo')->store('baseline-photos', 'public');

        $baseline = $outletModel->baselines()->create([
            'spot_label' => $data['spot_label'],
            'baseline_gps_lat' => $data['baseline_gps_lat'],
            'baseline_gps_lng' => $data['baseline_gps_lng'],
            'baseline_gps_radius_m' => $data['baseline_gps_radius_m'],
            'baseline_photo_path' => $photoPath,
            'captured_by' => $request->user()?->id,
            'captured_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        // A brand-new baseline can turn "no baseline GPS/photo registered"
        // flags on already-pending submissions at this outlet into real,
        // evaluated checks — the queue shouldn't keep showing the stale
        // "nothing to check yet" state.
        $this->qaReevaluation->reevaluateForOutlet($outletModel);

        return response()->json($this->present($baseline), 201);
    }

    public function update(Request $request, string $outlet, string $baseline)
    {
        $outletModel = $this->findOutlet($outlet);

        $baselineModel = $outletModel->baselines()->where('id', $baseline)->first();
        if (! $baselineModel) {
            throw ValidationException::withMessages([
                'baseline' => "No baseline [{$baseline}] registered for outlet [{$outlet}].",
            ]);
        }

        $data = $request->validate([
            'spot_label' => ['required', 'string', 'max:255'],
            'baseline_gps_lat' => ['required', 'numeric', 'between:-90,90'],
            'baseline_gps_lng' => ['required', 'numeric', 'between:-180,180'],
            'baseline_gps_radius_m' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:10240'],
        ]);

        if ($outletModel->baselines()->where('spot_label', $data['spot_label'])->where('id', '!=', $baselineModel->id)->exists()) {
            throw ValidationException::withMessages([
                'spot_label' => "This outlet already has a baseline registered for spot [{$data['spot_label']}].",
            ]);
        }

        $updates = [
            'spot_label' => $data['spot_label'],
            'baseline_gps_lat' => $data['baseline_gps_lat'],
            'baseline_gps_lng' => $data['baseline_gps_lng'],
            'baseline_gps_radius_m' => $data['baseline_gps_radius_m'],
            'notes' => $data['notes'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            $updates['baseline_photo_path'] = $request->file('photo')->store('baseline-photos', 'public');
            // A new reference photo invalidates any hash/embedding computed
            // against the old one — BaselineVisualSimilarityRule/
            // BaselineLocationRule recompute lazily the next time they need it.
            $updates['baseline_phash'] = null;
            $updates['baseline_embedding'] = null;
        }

        $baselineModel->update($updates);

        // The GPS point and/or reference photo just changed — every
        // pending submission at this outlet was checked against the OLD
        // values, so re-run Layer 1 QA against the current ones.
        $this->qaReevaluation->reevaluateForOutlet($outletModel);

        return response()->json($this->present($baselineModel->fresh()));
    }

    private function findOutlet(string $code): Outlet
    {
        $outlet = Outlet::where('code', $code)->first();
        if (! $outlet) {
            throw ValidationException::withMessages([
                'outlet' => "No outlet is registered with code [{$code}].",
            ]);
        }

        return $outlet;
    }

    private function present(OutletBaseline $baseline): array
    {
        return [
            'id' => (string) $baseline->id,
            'spot_label' => $baseline->spot_label,
            'baseline_gps_lat' => (float) $baseline->baseline_gps_lat,
            'baseline_gps_lng' => (float) $baseline->baseline_gps_lng,
            'baseline_gps_radius_m' => (int) $baseline->baseline_gps_radius_m,
            'photo_url' => $this->resolveUrl($baseline->baseline_photo_path),
            'captured_by' => $baseline->capturedBy?->name ?? 'QA Dashboard User',
            'captured_at' => optional($baseline->captured_at)->toIso8601String(),
            'notes' => $baseline->notes,
        ];
    }

    // Mirrors QaPresenter::resolveUrl (browser-facing — no
    // host.docker.internal substitution, unlike ImageServiceUrlResolver
    // which is for server-to-server image-service calls).
    private function resolveUrl(?string $ref): ?string
    {
        if ($ref === null) {
            return null;
        }

        if (Str::startsWith($ref, ['http://', 'https://'])) {
            return $ref;
        }

        return Storage::disk('public')->url($ref);
    }
}
