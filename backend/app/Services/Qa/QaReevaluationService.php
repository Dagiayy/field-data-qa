<?php

namespace App\Services\Qa;

use App\Enums\SubmissionStatus;
use App\Models\Outlet;
use App\Models\Quest;
use App\Models\Submission;
use App\Models\SubmissionTrustScore;
use App\Services\Trust\TrustScoreCalculator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Re-runs Layer 1 automated QA (and Trust Score) for every submission still
 * sitting in the QA queue when the baseline data it was checked against
 * changes — a submission flagged "no baseline GPS registered" or "no
 * duration baseline configured" shouldn't keep showing that stale flag
 * after a QA lead actually registers one in Baseline Management.
 *
 * Only submissions still awaiting a decision (pending_review, backcheck,
 * sent_back — the same set QueueController's default view shows) are
 * touched. A submission that's already been approved/rejected is a closed,
 * decided case — silently rewriting its automated flags after the fact
 * would contradict the human decision already recorded against it, so
 * those are deliberately left alone.
 *
 * Old QaFlag rows are deleted and replaced rather than kept alongside new
 * ones: unlike QaReview (the actual human-decision audit trail, which stays
 * strictly append-only per project CLAUDE.md), a QaFlag is a derived,
 * reproducible snapshot of "what Layer 1 currently sees" — keeping stale
 * copies around would just make the dashboard show contradictory flags for
 * the same submission.
 *
 * Runs synchronously in the request that changed the baseline (store/update
 * calls in OutletBaselineController / QuestBaselineController) — fine at
 * this system's beta scale (a handful of pending submissions per outlet or
 * quest). If that stops being true, this is the natural seam to move to a
 * queued job (the queue infrastructure already exists — see
 * SubmissionIngestionService's own comment on why its own QA run happens
 * post-commit) rather than blocking the HTTP response.
 */
class QaReevaluationService
{
    private const QUEUE_STATUSES = [
        SubmissionStatus::PendingReview->value,
        SubmissionStatus::Backcheck->value,
        SubmissionStatus::SentBack->value,
    ];

    public function __construct(
        private QaRuleEngine $qaRuleEngine,
        private TrustScoreCalculator $trustScoreCalculator,
    ) {
    }

    public function reevaluateForOutlet(Outlet $outlet): int
    {
        return $this->reevaluate(Submission::query()->where('outlet_id', $outlet->id));
    }

    public function reevaluateForQuest(Quest $quest): int
    {
        return $this->reevaluate(Submission::query()->where('quest_id', $quest->id));
    }

    private function reevaluate(Builder $query): int
    {
        $submissionIds = $query->whereIn('status', self::QUEUE_STATUSES)->pluck('id');

        foreach ($submissionIds as $submissionId) {
            // Reload fresh per submission rather than reusing an in-memory
            // instance — qaFlags() is about to be deleted out from under
            // it, and QaRuleEngine/TrustScoreCalculator both eager-load
            // relations that must reflect the current (post-delete) state.
            $submission = Submission::query()
                ->with(['quest', 'outlet.baselines', 'answers', 'media', 'prices'])
                ->find($submissionId);

            if (! $submission) {
                continue;
            }

            $submission->qaFlags()->delete();
            $submission->unsetRelation('qaFlags');

            $this->qaRuleEngine->run($submission);

            $score = $this->trustScoreCalculator->compute($submission->fresh(['quest', 'outlet.baselines', 'answers', 'media', 'prices', 'qaFlags', 'qaReviews']));

            SubmissionTrustScore::updateOrCreate(
                ['submission_id' => $submission->id],
                [
                    'gps_score' => $score['gps'],
                    'photo_score' => $score['photo'],
                    'time_score' => $score['time'],
                    'completeness_score' => $score['completeness'],
                    'audit_confirmation_score' => $score['audit_confirmation'],
                    'total_score' => $score['total'],
                    'weights_used' => $score['weights'],
                ]
            );
        }

        return $submissionIds->count();
    }
}
