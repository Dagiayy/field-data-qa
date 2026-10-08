<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RejectionReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'applies_to_quest_types',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'applies_to_quest_types' => 'array',
            'active' => 'boolean',
        ];
    }

    public function qaReviews(): HasMany
    {
        return $this->hasMany(QaReview::class, 'reason_code');
    }
}
