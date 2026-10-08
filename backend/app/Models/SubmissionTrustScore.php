<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionTrustScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'gps_score',
        'photo_score',
        'time_score',
        'completeness_score',
        'audit_confirmation_score',
        'total_score',
        'weights_used',
    ];

    protected function casts(): array
    {
        return [
            'gps_score' => 'decimal:2',
            'photo_score' => 'decimal:2',
            'time_score' => 'decimal:2',
            'completeness_score' => 'decimal:2',
            'audit_confirmation_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'weights_used' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
