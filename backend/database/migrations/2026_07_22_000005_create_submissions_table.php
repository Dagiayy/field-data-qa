<?php

use App\Enums\SubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('quest_id')->constrained('quests');
            $table->string('config_version');
            $table->string('agent_id');
            $table->foreignId('outlet_id')->constrained('outlets');
            $table->timestamp('survey_start_at');
            $table->timestamp('survey_end_at');
            $table->timestamp('submitted_at');
            $table->decimal('gps_lat', 10, 7);
            $table->decimal('gps_lng', 10, 7);
            $table->decimal('gps_accuracy_m', 8, 2)->nullable();
            $table->string('client_app_version')->nullable();
            $table->enum('status', array_column(SubmissionStatus::cases(), 'value'))
                ->default(SubmissionStatus::Received->value);
            $table->timestamps();

            $table->index('status');
            $table->index('outlet_id');
            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
