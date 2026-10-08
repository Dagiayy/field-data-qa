<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentTrustScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'approval_rate',
        'rejection_breakdown',
        'backcheck_pass_rate',
        'tier',
    ];

    protected function casts(): array
    {
        return [
            'approval_rate' => 'decimal:2',
            'rejection_breakdown' => 'array',
            'backcheck_pass_rate' => 'decimal:2',
        ];
    }
}
