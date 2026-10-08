<?php

use App\Enums\QaReviewDecision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit log: no updated_at column exists, so a QA decision
     * can never be edited in place — only new rows are ever inserted.
     */
    public function up(): void
    {
        Schema::create('qa_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('decision', array_column(QaReviewDecision::cases(), 'value'));
            $table->foreignId('reason_code')->nullable()->constrained('rejection_reasons')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_reviews');
    }
};
