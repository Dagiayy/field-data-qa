<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cached results from the image-service's /analyze endpoint (OCR text,
     * legibility confidence, size/framing proxy, exposure) — computed once
     * at ingestion time, same pattern as `phash`.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->text('ocr_text')->nullable()->after('phash');
            $table->float('ocr_avg_confidence')->nullable()->after('ocr_text');
            $table->float('text_height_ratio')->nullable()->after('ocr_avg_confidence');
            $table->float('brightness_mean')->nullable()->after('text_height_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['ocr_text', 'ocr_avg_confidence', 'text_height_ratio', 'brightness_mean']);
        });
    }
};
