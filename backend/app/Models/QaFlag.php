<?php

namespace App\Models;

use App\Enums\QaFlagResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QaFlag extends Model
{
    use HasFactory;

    protected $table = 'qa_flags';

    protected $fillable = [
        'submission_id',
        'rule_name',
        'result',
        'severity',
        'detail',
    ];

    protected function casts(): array
    {
        return [
            'result' => QaFlagResult::class,
            'detail' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
