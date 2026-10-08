<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cached OpenCLIP embedding of the baseline reference photo itself, so
     * the QA engine doesn't re-download/re-embed it on every submission —
     * same pattern as `baseline_phash`.
     */
    public function up(): void
    {
        Schema::table('outlet_baselines', function (Blueprint $table) {
            $table->json('baseline_embedding')->nullable()->after('baseline_phash');
        });
    }

    public function down(): void
    {
        Schema::table('outlet_baselines', function (Blueprint $table) {
            $table->dropColumn('baseline_embedding');
        });
    }
};
