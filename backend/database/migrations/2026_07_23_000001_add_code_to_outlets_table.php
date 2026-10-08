<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ingestion envelopes identify an outlet by a business-facing string
     * (e.g. "OUT-SHOA-BOLE-014"), not our internal auto-increment id. `code`
     * is that lookup key, mirroring how `quests.form_code` already works.
     */
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
