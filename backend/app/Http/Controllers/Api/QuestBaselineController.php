<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutletBaseline;
use App\Models\Quest;
use App\Models\QuestBaselinePrice;
use App\Services\Qa\QaReevaluationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Quest-level Baseline Management — everything a quest's submissions get
 * validated against, from one place: the GPS/reference-photo spots
 * (physically belonging to the quest's one outlet, via Quest::outlet_id)
 * plus the expected survey duration range and expected price range per SKU
 * (belonging to the quest itself). One quest, one screen, every baseline
 * type it needs.
 */
class QuestBaselineController extends Controller
{
    public function __construct(private QaReevaluationService $qaReevaluation)
    {
    }

    public function show(Request $request, string $quest)
    {
        $questModel = $this->findQuest($quest)->load(['baselinePrices', 'outlet.baselines']);

        return response()->json($this->present($questModel));
    }

    public function update(Request $request, string $quest)
    {
        $questModel = $this->findQuest($quest);

        $data = $request->validate([
            // No gte:expected_duration_min_seconds here — Laravel's `gte`
            // rule mishandles the "max set, min left blank" case (comparing
            // against a genuinely absent field), so the min<=max check is
            // done manually below instead, only when both are actually present.
            'expected_duration_min_seconds' => ['nullable', 'integer', 'min:0'],
            'expected_duration_max_seconds' => ['nullable', 'integer', 'min:0'],
            'price_ranges' => ['array'],
            'price_ranges.*.sku_id' => ['required', 'string'],
            'price_ranges.*.min' => ['required', 'numeric', 'min:0'],
            // Not `gte:price_ranges.*.min` — cross-field wildcard comparison
            // rules are unreliable across Laravel versions for per-row
            // (same-index) matching, so this is checked manually below instead.
            'price_ranges.*.max' => ['required', 'numeric'],
            'price_ranges.*.currency' => ['required', 'string', 'max:8'],
            'price_ranges.*.unit' => ['nullable', 'string', 'max:64'],
        ]);

        if (
            isset($data['expected_duration_min_seconds'], $data['expected_duration_max_seconds'])
            && $data['expected_duration_max_seconds'] < $data['expected_duration_min_seconds']
        ) {
            throw ValidationException::withMessages([
                'expected_duration_max_seconds' => 'Maximum duration must be greater than or equal to the minimum.',
            ]);
        }

        foreach ($data['price_ranges'] ?? [] as $idx => $range) {
            if ($range['max'] < $range['min']) {
                throw ValidationException::withMessages([
                    "price_ranges.{$idx}.max" => "Row {$idx}: max must be greater than or equal to min.",
                ]);
            }
        }

        DB::transaction(function () use ($questModel, $data) {
            $questModel->update([
                'expected_duration_min_seconds' => $data['expected_duration_min_seconds'] ?? null,
                'expected_duration_max_seconds' => $data['expected_duration_max_seconds'] ?? null,
            ]);

            // Whole-set replace rather than a diff/patch — the form always
            // submits its complete current row set, so "the SKU I deleted
            // in the form" needs to actually disappear, not linger because
            // nothing told the backend to remove it.
            $submittedSkus = collect($data['price_ranges'] ?? [])->pluck('sku_id')->all();
            $questModel->baselinePrices()->whereNotIn('sku_id', $submittedSkus)->delete();

            foreach ($data['price_ranges'] ?? [] as $range) {
                QuestBaselinePrice::updateOrCreate(
                    ['quest_id' => $questModel->id, 'sku_id' => $range['sku_id']],
                    [
                        'min' => $range['min'],
                        'max' => $range['max'],
                        'currency' => $range['currency'],
                        'unit' => $range['unit'] ?? null,
                    ]
                );
            }
        });

        // Duration/price baselines just changed (or were set for the first
        // time) — SurveyDurationRule and MarketPriceRangeResolver both read
        // them live, so every pending submission for this quest needs its
        // flags/Trust Score re-run against the current values rather than
        // whatever was (or wasn't) configured when it was first ingested.
        $this->qaReevaluation->reevaluateForQuest($questModel);

        return response()->json($this->present($questModel->fresh(['baselinePrices', 'outlet.baselines'])));
    }

    private function findQuest(string $formCode): Quest
    {
        $quest = Quest::where('form_code', $formCode)->first();
        if (! $quest) {
            throw ValidationException::withMessages([
                'quest' => "No quest is registered with form_code [{$formCode}].",
            ]);
        }

        return $quest;
    }

    private function present(Quest $quest): array
    {
        return [
            'quest_id' => $quest->form_code,
            'expected_duration_min_seconds' => $quest->expected_duration_min_seconds,
            'expected_duration_max_seconds' => $quest->expected_duration_max_seconds,
            'has_duration_baseline' => $quest->hasDurationBaseline(),
            'price_ranges' => $quest->baselinePrices->map(fn (QuestBaselinePrice $p) => [
                'sku_id' => $p->sku_id,
                'min' => (float) $p->min,
                'max' => (float) $p->max,
                'currency' => $p->currency,
                'unit' => $p->unit,
            ])->all(),
            'outlet' => $quest->outlet ? [
                'id' => $quest->outlet->code,
                'name' => $quest->outlet->name,
                'gps_lat' => (float) $quest->outlet->gps_lat,
                'gps_lng' => (float) $quest->outlet->gps_lng,
                'baselines' => $quest->outlet->baselines->map(fn (OutletBaseline $b) => [
                    'id' => (string) $b->id,
                    'spot_label' => $b->spot_label,
                    'baseline_gps_lat' => (float) $b->baseline_gps_lat,
                    'baseline_gps_lng' => (float) $b->baseline_gps_lng,
                    'baseline_gps_radius_m' => (int) $b->baseline_gps_radius_m,
                    'photo_url' => $this->resolveUrl($b->baseline_photo_path),
                    'notes' => $b->notes,
                ])->values()->all(),
            ] : null,
        ];
    }

    // Mirrors OutletBaselineController::resolveUrl / QaPresenter::resolveUrl.
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
