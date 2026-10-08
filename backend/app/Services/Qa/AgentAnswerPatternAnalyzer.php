<?php

namespace App\Services\Qa;

use App\Enums\QaFlagResult;
use App\Models\Answer;
use App\Models\Submission;
use Illuminate\Support\Collection;

/**
 * Shared engine behind Answer Pattern Validation. Looks at one agent's own
 * submission history and measures, per (quest_id, question_id), how often
 * they gave the same answer — the low-effort "always says No" signal.
 *
 * Grouping is always scoped by quest_id as well as question_id: quests are
 * configurable and dynamic, so a question_id string is only guaranteed to
 * mean the same thing (same options, same context) within one quest — two
 * different quest configs could coincidentally reuse a question_id for
 * something unrelated, and pooling those together would produce a
 * meaningless ratio.
 *
 * Used two ways:
 *  - AnswerPatternRule calls breakdownForAgent() at ingestion time and keeps
 *    only the rows for the submission just received, to decide whether to
 *    raise a QaFlag on it.
 *  - The agents dashboard calls it directly for the full per-agent picture
 *    across every quest/question the agent has ever answered.
 */
class AgentAnswerPatternAnalyzer
{
    public const MIN_SAMPLE = 5;

    public const SEVERE_THRESHOLD = 0.95; // 95%+ -> Fail

    public const MODERATE_THRESHOLD = 0.85; // 85%+ -> Flag

    // Consecutive-repetition streak lengths used by
    // currentSubmissionStreaks()/classifyStreak() — see their docblocks.
    public const STREAK_FAIL = 8;

    public const STREAK_FLAG = 5;

    // A streak must span at least this many distinct outlets before it's
    // treated as evidence of low-effort behavior — see currentSubmissionStreaks().
    public const MIN_DISTINCT_OUTLETS = 2;

    public const CHECKABLE_INPUT_TYPES = [
        'single_choice',
        'dropdown',
        'checkbox',
        'multiple_choice',
        'repeat_table',
    ];

    /**
     * @return Collection<int, array{quest_id: int, question_id: string, dominant_value: string, dominant_count: int, total: int, ratio: float}>
     */
    public function breakdownForAgent(string $agentId, ?int $lookbackPerQuest = null): Collection
    {
        $submissions = Submission::query()
            ->where('agent_id', $agentId)
            ->orderByDesc('submitted_at')
            ->get(['id', 'quest_id']);

        if ($submissions->isEmpty()) {
            return collect();
        }

        $submissionIds = $submissions
            ->groupBy('quest_id')
            ->flatMap(fn (Collection $subs) => $lookbackPerQuest ? $subs->take($lookbackPerQuest) : $subs)
            ->pluck('id');

        $questIdBySubmission = $submissions->pluck('quest_id', 'id');

        $answers = Answer::query()
            ->whereIn('submission_id', $submissionIds)
            ->whereIn('input_type', self::CHECKABLE_INPUT_TYPES)
            ->get(['submission_id', 'question_id', 'value']);

        return $answers
            ->groupBy(fn (Answer $a) => $questIdBySubmission[$a->submission_id].'::'.$a->question_id)
            ->map(function (Collection $group) use ($questIdBySubmission) {
                $first = $group->first();
                $values = $group->map(fn (Answer $a) => $this->normalizeValue($a->value));
                $total = $values->count();
                $counts = $values->countBy();
                $dominantValue = $counts->sortDesc()->keys()->first();
                $dominantCount = $counts->max();

                return [
                    'quest_id' => $questIdBySubmission[$first->submission_id],
                    'question_id' => $first->question_id,
                    'dominant_value' => (string) $dominantValue,
                    'dominant_count' => $dominantCount,
                    'total' => $total,
                    'ratio' => $total > 0 ? round($dominantCount / $total, 4) : 0.0,
                ];
            })
            ->filter(fn (array $row) => $row['total'] >= self::MIN_SAMPLE)
            ->values();
    }

    /**
     * Real "low-effort repetition" detection for a single freshly-ingested
     * submission: for each checkable question it answered, walk the agent's
     * own submission history for that exact quest — most recent first,
     * starting with this submission — and count how many submissions in a
     * row (including this one) gave the exact same normalized answer.
     *
     * This only flags a submission when its OWN answer is actually part of
     * the repeated run. The previous implementation flagged a (quest,
     * question) pair purely from the agent's aggregate historical ratio
     * over a rolling window, regardless of what this submission itself
     * answered — so an agent who broke the pattern and gave a different,
     * legitimate answer this time still got flagged solely because of old
     * history, and any question with few valid options (e.g. a 3-choice
     * dropdown) could trip the ratio by chance even with genuinely varied
     * answers. Requiring a real consecutive streak, anchored on the current
     * answer, fixes both.
     *
     * The streak must also span at least MIN_DISTINCT_OUTLETS different
     * outlets (the spec's own framing: repeats "regardless of outlet
     * context"). Without this, a repeat-visit quest to the same outlet — or
     * simply re-testing the same sample payload — trivially "repeats" every
     * answer including fields that are just facts about that one location
     * (region, zone, GPS confirmation, consent), which isn't low-effort
     * behavior at all: it's the same ground truth being reported honestly
     * each time. Requiring the pattern to hold across genuinely different
     * outlets is what actually makes it suspicious.
     *
     * @return Collection<int, array{question_id: string, dominant_value: string, streak_length: int}>
     */
    public function currentSubmissionStreaks(Submission $submission, int $lookbackPerQuest = 20): Collection
    {
        $checkableQuestionIds = $submission->answers
            ->filter(fn (Answer $a) => in_array($a->input_type, self::CHECKABLE_INPUT_TYPES, true))
            ->pluck('question_id')
            ->unique()
            ->values();

        if ($checkableQuestionIds->isEmpty()) {
            return collect();
        }

        // Most-recent-first, including the current submission — which is
        // guaranteed to be first (streaks are anchored on it) regardless of
        // how ties in submitted_at sort, since it's prepended explicitly.
        $priorSubmissions = Submission::query()
            ->where('agent_id', $submission->agent_id)
            ->where('quest_id', $submission->quest_id)
            ->where('id', '!=', $submission->id)
            ->orderByDesc('submitted_at')
            ->limit($lookbackPerQuest)
            ->get(['id', 'outlet_id']);

        $orderedSubmissions = collect([['id' => $submission->id, 'outlet_id' => $submission->outlet_id]])
            ->merge($priorSubmissions->map(fn (Submission $s) => ['id' => $s->id, 'outlet_id' => $s->outlet_id]));

        $orderedSubmissionIds = $orderedSubmissions->pluck('id');

        $answersByQuestion = Answer::query()
            ->whereIn('submission_id', $orderedSubmissionIds)
            ->whereIn('question_id', $checkableQuestionIds)
            ->get(['submission_id', 'question_id', 'value'])
            ->groupBy('question_id')
            ->map(fn (Collection $rows) => $rows->mapWithKeys(
                fn (Answer $a) => [$a->submission_id => $this->normalizeValue($a->value)]
            ));

        $results = collect();

        foreach ($checkableQuestionIds as $questionId) {
            $valueBySubmission = $answersByQuestion->get($questionId, collect());
            $currentValue = $valueBySubmission->get($submission->id);
            if ($currentValue === null) {
                continue;
            }

            $streak = 0;
            $outletsInStreak = [];
            foreach ($orderedSubmissions as $row) {
                $value = $valueBySubmission->get($row['id']);
                // A submission that didn't answer this question at all
                // (optional question, different config version) is simply
                // skipped rather than treated as a streak-breaker — it
                // wasn't a chance for the agent to vary their answer.
                if ($value === null) {
                    continue;
                }
                if ($value !== $currentValue) {
                    break;
                }
                $streak++;
                $outletsInStreak[$row['outlet_id']] = true;
            }

            if (count($outletsInStreak) < self::MIN_DISTINCT_OUTLETS) {
                continue;
            }

            $results->push([
                'question_id' => $questionId,
                'dominant_value' => $currentValue,
                'streak_length' => $streak,
            ]);
        }

        return $results;
    }

    /**
     * @return array{result: QaFlagResult, severity: string}|null
     */
    public static function classifyStreak(int $streakLength): ?array
    {
        if ($streakLength >= self::STREAK_FAIL) {
            return ['result' => QaFlagResult::Fail, 'severity' => 'high'];
        }

        if ($streakLength >= self::STREAK_FLAG) {
            return ['result' => QaFlagResult::Flag, 'severity' => 'medium'];
        }

        return null;
    }

    /**
     * @return array{result: QaFlagResult, severity: string}|null
     */
    public static function classify(float $ratio): ?array
    {
        if ($ratio >= self::SEVERE_THRESHOLD) {
            return ['result' => QaFlagResult::Fail, 'severity' => 'high'];
        }

        if ($ratio >= self::MODERATE_THRESHOLD) {
            return ['result' => QaFlagResult::Flag, 'severity' => 'medium'];
        }

        return null;
    }

    private function normalizeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return strtolower(trim((string) $value));
    }
}
