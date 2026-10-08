<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-dimension Trust Score weights an admin sets at quest-creation
     * time (see project CLAUDE.md "Trust Score Weighted Composite") — how
     * much gps/photo/time/price/answer_pattern each count toward the
     * weighted composite for submissions to THIS quest. Null means the
     * quest hasn't been configured yet and the calculator falls back to
     * TrustScoreCalculator::DEFAULT_WEIGHTS.
     */
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->json('trust_score_weights')->nullable()->after('required_question_ids');
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->dropColumn('trust_score_weights');
        });
    }
};
