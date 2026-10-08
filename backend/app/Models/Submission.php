<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Submission extends Model
{
    use HasFactory, HasUuids;

    /**
     * id is the submission_id from the ingestion envelope (the idempotency
     * key) — never auto-incremented, and HasUuids only fills it in when the
     * caller hasn't already set one.
     */
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'quest_id',
        'config_version',
        'agent_id',
        'outlet_id',
        'quest_accepted_at',
        'survey_start_at',
        'survey_end_at',
        'submitted_at',
        'gps_lat',
        'gps_lng',
        'gps_accuracy_m',
        'client_app_version',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quest_accepted_at' => 'datetime',
            'survey_start_at' => 'datetime',
            'survey_end_at' => 'datetime',
            'submitted_at' => 'datetime',
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'gps_accuracy_m' => 'decimal:2',
            'status' => SubmissionStatus::class,
        ];
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    public function qaFlags(): HasMany
    {
        return $this->hasMany(QaFlag::class);
    }

    public function qaReviews(): HasMany
    {
        return $this->hasMany(QaReview::class);
    }

    public function paymentStatus(): HasOne
    {
        return $this->hasOne(PaymentStatus::class);
    }

    public function trustScore(): HasOne
    {
        return $this->hasOne(SubmissionTrustScore::class);
    }
}
