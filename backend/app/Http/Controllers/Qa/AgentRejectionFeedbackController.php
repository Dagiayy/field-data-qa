<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Services\Qa\AgentRejectionAggregator;

/**
 * "Send back all the reasons to the agent, by their agent_id" — the
 * queryable side of that: every rejection this agent has ever received,
 * across every submission, with the reason code/label, the reviewer's note,
 * and when it happened. Actually delivering this to the agent (e.g. a
 * Telegram message from the Mini App) is a separate integration point this
 * system doesn't own (see project CLAUDE.md — the ingestion API is the only
 * thing the Mini App pipeline talks to); this endpoint is what such a
 * notifier, or a QA Lead looking the agent up directly, would read from.
 */
class AgentRejectionFeedbackController extends Controller
{
    public function __construct(private AgentRejectionAggregator $aggregator)
    {
    }

    public function show(string $agentId)
    {
        $rejections = $this->aggregator->rejectionsFor($agentId);

        return response()->json([
            'agent_id' => $agentId,
            'total_rejections' => $rejections->count(),
            'rejection_breakdown' => $this->aggregator->breakdown($rejections),
            'rejections' => $rejections->values(),
        ]);
    }
}
