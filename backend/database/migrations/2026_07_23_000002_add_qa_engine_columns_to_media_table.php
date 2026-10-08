<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `phash` is a perceptual hash computed at ingestion time (see
     * App\Services\ImageSimilarity) used for duplicate/reused-photo
     * detection. `matched_baseline_id` records which OutletBaseline spot
     * this photo was compared against, so the dashboard can show baseline
     * comparison details without recomputing the match.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('phash', 64)->nullable()->after('gps_at_capture_lng');
            $table->foreignId('matched_baseline_id')->nullable()->after('phash')
                ->constrained('outlet_baselines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('matched_baseline_id');
            $table->dropColumn('phash');
        });
    }
};
