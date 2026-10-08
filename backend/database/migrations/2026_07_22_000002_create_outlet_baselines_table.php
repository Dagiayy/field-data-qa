<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('spot_label');
            $table->decimal('baseline_gps_lat', 10, 7);
            $table->decimal('baseline_gps_lng', 10, 7);
            $table->unsignedInteger('baseline_gps_radius_m');
            $table->string('baseline_photo_path');
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['outlet_id', 'spot_label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_baselines');
    }
};
