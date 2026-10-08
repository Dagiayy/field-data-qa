<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestBaselinePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'quest_id',
        'sku_id',
        'min',
        'max',
        'currency',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'min' => 'decimal:2',
            'max' => 'decimal:2',
        ];
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }
}
