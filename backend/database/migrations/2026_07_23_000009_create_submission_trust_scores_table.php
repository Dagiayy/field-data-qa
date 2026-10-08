<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per submission — the weighted Trust Score composite computed
     * right after Layer 1 QA runs (see App\Services\Trust\TrustScoreCalculator).
     * weights_used is a snapshot of the quest's trust_score_weights at
     * computation time, so a later admin change to a quest's weights never
     * silently rewrites the history of a score that already fed a payment
     * decision.
     */
    public function up(): void
    {
        Schema::create('submission_trust_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('submission_id')->constrained('submissions')->cascadeOnDelete()->unique();
            $table->decimal('gps_score', 5, 2);
            $table->decimal('photo_score', 5, 2);
            $table->decimal('time_score', 5, 2);
            $table->decimal('price_score', 5, 2);
            $table->decimal('answer_score', 5, 2);
            $table->decimal('total_score', 5, 2);
            $table->json('weights_used');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_trust_scores');
    }
};
