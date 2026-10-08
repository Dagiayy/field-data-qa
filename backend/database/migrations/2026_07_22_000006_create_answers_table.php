<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->string('question_id');
            $table->string('input_type');
            $table->string('row_id')->nullable();
            $table->json('value')->nullable();
            $table->string('media_ref')->nullable();
            $table->timestamps();

            $table->index('submission_id');
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
