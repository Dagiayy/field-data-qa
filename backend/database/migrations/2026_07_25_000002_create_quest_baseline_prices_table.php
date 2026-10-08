<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-configured expected price range per (quest, sku), set once via
     * the Baseline Management screen — this is the "baseline" counterpart to
     * the ingestion payload's own prices[].market_range_price. See
     * MarketPriceRangeResolver: the payload-supplied range is checked first,
     * this table is the fallback when the payload didn't send one.
     */
    public function up(): void
    {
        Schema::create('quest_baseline_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quest_id')->constrained('quests')->cascadeOnDelete();
            $table->string('sku_id');
            $table->decimal('min', 12, 2);
            $table->decimal('max', 12, 2);
            $table->string('currency');
            $table->string('unit')->nullable();
            $table->timestamps();

            $table->unique(['quest_id', 'sku_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_baseline_prices');
    }
};
