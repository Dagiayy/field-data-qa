<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletBaseline extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'spot_label',
        'baseline_gps_lat',
        'baseline_gps_lng',
        'baseline_gps_radius_m',
        'baseline_photo_path',
        'baseline_phash',
        'baseline_embedding',
        'captured_by',
        'captured_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'baseline_gps_lat' => 'decimal:7',
            'baseline_gps_lng' => 'decimal:7',
            'baseline_gps_radius_m' => 'integer',
            'captured_at' => 'datetime',
            'baseline_embedding' => 'array',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
