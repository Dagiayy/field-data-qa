<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Denormalized at ingestion time from the matching quest_acceptances row
     * (resolved by agent_id + quest_id + outlet_id, most recent acceptance
     * before survey_start_at) — nullable because a submission can arrive
     * without a matching acceptance ever having been recorded.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('quest_accepted_at')->nullable()->after('survey_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('quest_accepted_at');
        });
    }
};
