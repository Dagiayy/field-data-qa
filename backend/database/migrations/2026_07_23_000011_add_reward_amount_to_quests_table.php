<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The flat per-submission payout an admin sets on a quest at creation
     * time — what PaymentStatusDeriver pays out the instant a submission on
     * that quest is approved (see App\Services\Payments\PaymentStatusDeriver).
     */
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->decimal('reward_amount', 10, 2)->nullable()->after('max_submissions_per_outlet');
            $table->string('reward_currency', 3)->default('ETB')->after('reward_amount');
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->dropColumn(['reward_amount', 'reward_currency']);
        });
    }
};
