<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Price extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'question_id',
        'sku_id',
        'row_id',
        'value',
        'currency',
        'unit',
        'market_range_min',
        'market_range_max',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'market_range_min' => 'decimal:2',
            'market_range_max' => 'decimal:2',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
