<?php

namespace App\Models;

use App\Enums\QaReviewDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only: rows are only ever inserted, never updated. There is no
 * updated_at column, so there is nothing to mutate after the fact — the
 * only way to change a submission's QA outcome is to insert a new review.
 */
class QaReview extends Model
{
    use HasFactory;

    protected $table = 'qa_reviews';

    const UPDATED_AT = null;

    protected $fillable = [
        'submission_id',
        'reviewer_id',
        'decision',
        'reason_code',
        'note',
        'backcheck_outcome',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => QaReviewDecision::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(RejectionReason::class, 'reason_code');
    }
}
