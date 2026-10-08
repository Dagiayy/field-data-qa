<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'chain',
        'branch',
        'outlet_type',
        'city',
        'area',
        'gps_lat',
        'gps_lng',
        'gps_radius_m',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'gps_radius_m' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function baselines(): HasMany
    {
        return $this->hasMany(OutletBaseline::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
