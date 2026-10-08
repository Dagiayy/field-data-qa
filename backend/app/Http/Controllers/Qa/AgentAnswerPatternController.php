<?php

namespace App\Http\Controllers\Qa;

use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Services\Qa\AgentAnswerPatternAnalyzer;

/**
 * Agent-level view of Answer Pattern Validation: every (quest, question) an
 * agent has answered enough times to judge, with the dominant-answer ratio
 * and whether it crosses the flag/fail threshold. This is the aggregate
 * counterpart to the per-submission flags AnswerPatternRule raises — it lets
 * a QA Lead see the pattern across an agent's whole history in one place,
 * not just buried inside one submission's flag list.
 */
class AgentAnswerPatternController extends Controller
{
    public function __construct(private AgentAnswerPatternAnalyzer $analyzer)
    {
    }

    public function show(string $agentId)
    {
        $breakdown = $this->analyzer->breakdownForAgent($agentId);

        $questTitles = Quest::whereIn('id', $breakdown->pluck('quest_id')->unique())
            ->get(['id', 'form_code', 'title'])
            ->keyBy('id');

        $quests = $breakdown
            ->groupBy('quest_id')
            ->map(function ($rows, $questId) use ($questTitles) {
                $quest = $questTitles->get((int) $questId);

                return [
                    'quest_id' => $quest?->form_code,
                    'quest_title' => $quest?->title,
                    'questions' => $rows
                        ->map(fn (array $row) => $this->questionRow($row))
                        ->sortByDesc('repetition_ratio')
                        ->values(),
                ];
            })
            ->values();

        $flaggedRows = $breakdown->filter(
            fn (array $row) => AgentAnswerPatternAnalyzer::classify($row['ratio']) !== null
        );

        return response()->json([
            'agent_id' => $agentId,
            'questions_evaluated' => $breakdown->count(),
            'questions_flagged' => $flaggedRows->count(),
            'worst_repetition_ratio' => $breakdown->isNotEmpty()
                ? round($breakdown->max('ratio') * 100, 1)
                : null,
            'quests' => $quests,
        ]);
    }

    private function questionRow(array $row): array
    {
        $classification = AgentAnswerPatternAnalyzer::classify($row['ratio']);

        return [
            'question_id' => $row['question_id'],
            'dominant_value' => $row['dominant_value'],
            'dominant_count' => $row['dominant_count'],
            'total_evaluations' => $row['total'],
            'repetition_ratio' => round($row['ratio'] * 100, 1),
            'result' => $classification['result']->value ?? 'pass',
            'severity' => $classification['severity'] ?? null,
        ];
    }
}
