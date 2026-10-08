<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_trust_scores', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->unique();
            $table->decimal('approval_rate', 5, 2)->nullable();
            $table->json('rejection_breakdown')->nullable();
            $table->decimal('backcheck_pass_rate', 5, 2)->nullable();
            $table->string('tier')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_trust_scores');
    }
};
