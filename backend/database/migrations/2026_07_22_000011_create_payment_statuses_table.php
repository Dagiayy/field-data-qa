<?php

use App\Enums\PaymentWalletState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('submission_id')->unique()->constrained('submissions')->cascadeOnDelete();
            $table->string('agent_id');
            $table->enum('wallet_state', array_column(PaymentWalletState::cases(), 'value'))
                ->default(PaymentWalletState::Pending->value);
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamps();

            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_statuses');
    }
};
