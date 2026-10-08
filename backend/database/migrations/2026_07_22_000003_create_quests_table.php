<?php

use App\Enums\QuestType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quests', function (Blueprint $table) {
            $table->id();
            $table->string('form_code')->unique();
            $table->string('title');
            $table->enum('quest_type', array_column(QuestType::cases(), 'value'));
            $table->string('config_version');
            $table->decimal('geofence_center_lat', 10, 7);
            $table->decimal('geofence_center_lng', 10, 7);
            $table->unsignedInteger('geofence_radius_m');
            $table->timestamp('collection_window_start')->nullable();
            $table->timestamp('collection_window_end')->nullable();
            $table->unsignedInteger('max_submissions_per_outlet')->nullable();
            $table->json('required_question_ids')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quests');
    }
};
