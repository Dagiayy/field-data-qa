<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records the moment an agent accepts a quest at a specific shop — a
     * pre-submission event reported by the Mini App, independent of whether
     * a submission ever follows. Feeds the accept-to-start time validation
     * and (later) the Trust Score's behavioral signal.
     */
    public function up(): void
    {
        Schema::create('quest_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quest_id')->constrained('quests');
            $table->foreignId('outlet_id')->constrained('outlets');
            $table->string('agent_id');
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->index(['agent_id', 'quest_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_acceptances');
    }
};
