<?php

namespace App\Http\Controllers\Qa;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\QaReview;
use App\Models\Submission;
use App\Models\SubmissionTrustScore;
use App\Services\Qa\AgentRejectionAggregator;
use App\Services\Trust\TrustTier;

/**
 * The real backend behind the (previously entirely mocked)
 * frontend/src/pages/AgentTrustScore.tsx dashboard page — everything on it
 * is now a genuine, traceable figure derived from this agent's own
 * submissions, QA reviews, and Trust Score history, not a hardcoded demo
 * number.
 */
class AgentTrustScoreController extends Controller
{
    private const RECENT_BUILDS_LIMIT = 10;

    public function __construct(private AgentRejectionAggregator $rejectionAggregator)
    {
    }

    public function show(string $agentId)
    {
        $overallTrustScore = SubmissionTrustScore::query()
            ->join('submissions', 'submissions.id', '=', 'submission_trust_scores.submission_id')
            ->where('submissions.agent_id', $agentId)
            ->avg('submission_trust_scores.total_score');

        $approvedCount = Submission::where('agent_id', $agentId)->where('status', SubmissionStatus::Approved->value)->count();
        $rejectedCount = Submission::where('agent_id', $agentId)->where('status', SubmissionStatus::Rejected->value)->count();
        $decidedCount = $approvedCount + $rejectedCount;
        $approvalRate = $decidedCount > 0 ? round(($approvedCount / $decidedCount) * 100, 2) : 100.0;

        $backcheckReviews = QaReview::query()
            ->whereHas('submission', fn ($q) => $q->where('agent_id', $agentId))
            ->whereNotNull('backcheck_outcome')
            ->get();
        $confirmedCount = $backcheckReviews->where('backcheck_outcome', 'confirmed')->count();
        $backcheckPassRate = $backcheckReviews->isNotEmpty()
            ? round(($confirmedCount / $backcheckReviews->count()) * 100, 2)
            : 100.0;

        $rejections = $this->rejectionAggregator->rejectionsFor($agentId);

        $recentBuilds = Submission::query()
            ->where('agent_id', $agentId)
            ->with(['quest', 'trustScore'])
            ->whereHas('trustScore')
            ->orderByDesc('submitted_at')
            ->limit(self::RECENT_BUILDS_LIMIT)
            ->get()
            ->map(fn (Submission $submission) => $this->recentBuild($submission));

        return response()->json([
            'agent_id' => $agentId,
            'overall_trust_score' => $overallTrustScore !== null ? round((float) $overallTrustScore, 2) : null,
            'approval_rate' => $approvalRate,
            'backcheck_pass_rate' => $backcheckPassRate,
            'tier' => TrustTier::forScore($overallTrustScore !== null ? (float) $overallTrustScore : null),
            'rejection_breakdown' => $this->rejectionAggregator->breakdown($rejections),
            'recent_builds' => $recentBuilds,
        ]);
    }

    private function recentBuild(Submission $submission): array
    {
        $score = $submission->trustScore;

        return [
            'submission_id' => $submission->id,
            'quest_title' => $submission->quest?->title,
            'date' => optional($submission->submitted_at)->toIso8601String(),
            'trust_score' => (float) $score->total_score,
            'gps_score' => (float) $score->gps_score,
            'time_score' => (float) $score->time_score,
            'photo_score' => (float) $score->photo_score,
            'completeness_score' => (float) $score->completeness_score,
            'audit_confirmation_score' => (float) $score->audit_confirmation_score,
            'outcome' => $this->outcomeFor($submission->status),
        ];
    }

    private function outcomeFor(SubmissionStatus $status): string
    {
        return match ($status) {
            SubmissionStatus::Approved => 'approved',
            SubmissionStatus::Rejected, SubmissionStatus::AutoRejected => 'rejected',
            default => 'pending',
        };
    }

}
