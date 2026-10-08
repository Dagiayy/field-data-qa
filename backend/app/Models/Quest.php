<?php

namespace App\Models;

use App\Enums\QuestType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quest extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_code',
        'title',
        'quest_type',
        'outlet_id',
        'config_version',
        'geofence_center_lat',
        'geofence_center_lng',
        'geofence_radius_m',
        'collection_window_start',
        'collection_window_end',
        'max_submissions_per_outlet',
        'reward_amount',
        'reward_currency',
        'required_question_ids',
        'trust_score_weights',
        'active',
        'expected_duration_min_seconds',
        'expected_duration_max_seconds',
    ];

    protected function casts(): array
    {
        return [
            'quest_type' => QuestType::class,
            'geofence_center_lat' => 'decimal:7',
            'geofence_center_lng' => 'decimal:7',
            'geofence_radius_m' => 'integer',
            'collection_window_start' => 'datetime',
            'collection_window_end' => 'datetime',
            'max_submissions_per_outlet' => 'integer',
            'reward_amount' => 'decimal:2',
            'required_question_ids' => 'array',
            'trust_score_weights' => 'array',
            'active' => 'boolean',
            'expected_duration_min_seconds' => 'integer',
            'expected_duration_max_seconds' => 'integer',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * The one outlet this quest is actually run at — lets Baseline
     * Management manage GPS/photo (which physically belongs to the outlet)
     * and duration/price (which belongs to the quest) from a single
     * quest-centric screen. Nullable: a quest can exist before its outlet
     * assignment is finalized.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function baselinePrices(): HasMany
    {
        return $this->hasMany(QuestBaselinePrice::class);
    }

    /**
     * A quest's duration baseline is "configured" once either bound is set
     * — SurveyDurationRule and the dashboard both use this to distinguish
     * "no baseline set up yet" (needs attention) from "duration checked and
     * within range" (a real pass).
     */
    public function hasDurationBaseline(): bool
    {
        return $this->expected_duration_min_seconds !== null || $this->expected_duration_max_seconds !== null;
    }
}
