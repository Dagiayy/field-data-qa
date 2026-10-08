<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cached perceptual hash of the baseline reference photo itself, so the
     * QA engine doesn't re-download/re-hash it on every submission.
     */
    public function up(): void
    {
        Schema::table('outlet_baselines', function (Blueprint $table) {
            $table->string('baseline_phash', 64)->nullable()->after('baseline_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('outlet_baselines', function (Blueprint $table) {
            $table->dropColumn('baseline_phash');
        });
    }
};
