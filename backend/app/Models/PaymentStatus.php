<?php

namespace App\Models;

use App\Enums\PaymentWalletState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Written only by the payment-status derivation logic (Phase 5) — never set
 * independently of a QA decision. Do not add a controller/route that lets
 * this be updated directly.
 */
class PaymentStatus extends Model
{
    use HasFactory;

    protected $table = 'payment_statuses';

    protected $fillable = [
        'submission_id',
        'agent_id',
        'wallet_state',
        'amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'wallet_state' => PaymentWalletState::class,
            'amount' => 'decimal:2',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
