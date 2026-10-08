<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('chain')->nullable();
            $table->string('branch')->nullable();
            $table->string('outlet_type')->nullable();
            $table->string('city')->nullable();
            $table->string('area')->nullable();
            $table->decimal('gps_lat', 10, 7);
            $table->decimal('gps_lng', 10, 7);
            $table->unsignedInteger('gps_radius_m');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
