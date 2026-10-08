<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('media_ref');
            $table->foreignUuid('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->string('question_id');
            $table->string('row_id')->nullable();
            $table->string('media_type');
            $table->string('file_path');
            $table->timestamp('captured_at');
            $table->decimal('gps_at_capture_lat', 10, 7)->nullable();
            $table->decimal('gps_at_capture_lng', 10, 7)->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'media_ref']);
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
