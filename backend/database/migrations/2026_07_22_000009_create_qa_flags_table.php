<?php

use App\Enums\QaFlagResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->string('rule_name');
            $table->enum('result', array_column(QaFlagResult::cases(), 'value'));
            $table->string('severity')->nullable();
            $table->json('detail')->nullable();
            $table->timestamps();

            $table->index('submission_id');
            $table->index('rule_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_flags');
    }
};
