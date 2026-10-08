<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured counterpart to the free-text "(Backcheck Outcome: ...)"
     * that was only ever appended to `note` — needed so an agent's
     * backcheck_pass_rate can be computed reliably instead of parsing note
     * text. Nullable: only set when a reviewer actually records a backcheck
     * outcome (see ConfirmActionModal's isBackcheckPage flow).
     */
    public function up(): void
    {
        Schema::table('qa_reviews', function (Blueprint $table) {
            $table->string('backcheck_outcome')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('qa_reviews', function (Blueprint $table) {
            $table->dropColumn('backcheck_outcome');
        });
    }
};
