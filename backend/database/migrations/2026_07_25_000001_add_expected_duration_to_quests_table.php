<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quest-level Baseline Management (project CLAUDE.md Phase 2 follow-up):
     * an admin-configured expected survey duration range, set once per
     * quest_id via the Baseline Management screen. Both null means the
     * quest hasn't had a duration baseline configured yet — SurveyDurationRule
     * treats that as "needs baseline setup", not as "duration passed".
     */
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->unsignedInteger('expected_duration_min_seconds')->nullable()->after('max_submissions_per_outlet');
            $table->unsignedInteger('expected_duration_max_seconds')->nullable()->after('expected_duration_min_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->dropColumn(['expected_duration_min_seconds', 'expected_duration_max_seconds']);
        });
    }
};
