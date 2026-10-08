<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extra quality metrics from the image-service's /analyze endpoint
     * (sharpness/blur, contrast, glare, noise — resolution and brightness
     * were already covered) plus a cached OpenCLIP embedding vector used for
     * baseline-vs-submitted-photo similarity (see ImageEmbeddingService).
     * Same "compute once at ingestion, cache on the row" pattern as `phash`.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->unsignedInteger('image_width')->nullable()->after('brightness_mean');
            $table->unsignedInteger('image_height')->nullable()->after('image_width');
            $table->float('sharpness_laplacian_var')->nullable()->after('image_height');
            $table->float('contrast_std_dev')->nullable()->after('sharpness_laplacian_var');
            $table->float('glare_ratio_pct')->nullable()->after('contrast_std_dev');
            $table->float('noise_sigma')->nullable()->after('glare_ratio_pct');
            $table->json('embedding')->nullable()->after('noise_sigma');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn([
                'image_width', 'image_height', 'sharpness_laplacian_var',
                'contrast_std_dev', 'glare_ratio_pct', 'noise_sigma', 'embedding',
            ]);
        });
    }
};
