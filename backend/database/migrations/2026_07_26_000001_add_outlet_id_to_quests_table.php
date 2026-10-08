<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a quest to the one outlet it's actually run at — nullable
     * because a quest could in principle be created before its outlet is
     * assigned. This is what lets Baseline Management manage a quest's GPS
     * + photo baseline (which physically belongs to the outlet) from the
     * same screen as its duration/price baseline (which belongs to the
     * quest) without asking the user to separately pick an outlet.
     */
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->foreignId('outlet_id')->nullable()->after('id')->constrained('outlets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('outlet_id');
        });
    }
};
