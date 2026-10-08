<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MarketRangePrice comes from the incoming ingestion payload itself
     * (prices[].market_range_price, e.g. "350-450" or a single value like
     * "410") — not a pre-configured admin reference table. If the payload
     * doesn't provide it, both columns stay null and the price simply has
     * no expected-range comparison, rather than falling back to a guess.
     */
    public function up(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->decimal('market_range_min', 14, 2)->nullable()->after('unit');
            $table->decimal('market_range_max', 14, 2)->nullable()->after('market_range_min');
        });
    }

    public function down(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->dropColumn(['market_range_min', 'market_range_max']);
        });
    }
};
