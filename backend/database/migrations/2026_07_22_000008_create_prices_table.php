<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->string('question_id');
            $table->string('sku_id');
            $table->string('row_id')->nullable();
            $table->decimal('value', 14, 2);
            $table->string('currency', 3);
            $table->string('unit')->nullable();
            $table->timestamps();

            $table->index('submission_id');
            $table->index('sku_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
