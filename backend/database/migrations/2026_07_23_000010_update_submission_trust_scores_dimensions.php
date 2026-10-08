<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trust Score dimensions changed from (gps, photo, time, price,
     * answer_pattern) to (gps, photo, time, completeness, audit_confirmation)
     * per the finalized grade distribution — price is no longer part of the
     * weighted composite (PriceRangeRule still raises its own QaFlag, it
     * just doesn't feed Trust Score), "completeness" (required-fields
     * presence) is its own dimension instead of being folded into answer
     * pattern, and "audit confirmation" (was this submission's manual QA
     * review confirmed?) is new.
     */
    public function up(): void
    {
        Schema::table('submission_trust_scores', function (Blueprint $table) {
            $table->dropColumn('price_score');
            $table->renameColumn('answer_score', 'completeness_score');
            $table->decimal('audit_confirmation_score', 5, 2)->after('completeness_score');
        });
    }

    public function down(): void
    {
        Schema::table('submission_trust_scores', function (Blueprint $table) {
            $table->dropColumn('audit_confirmation_score');
            $table->renameColumn('completeness_score', 'answer_score');
            $table->decimal('price_score', 5, 2)->after('time_score');
        });
    }
};
