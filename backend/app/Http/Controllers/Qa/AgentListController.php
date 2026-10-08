<?php

namespace App\Http\Controllers\Qa;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\SubmissionTrustScore;
use App\Services\Trust\TrustTier;
use Illuminate\Support\Carbon;

/**
 * The "which agents exist and how are they doing" overview — every agent
 * who has ever submitted anything, with their real overall Trust Score,
 * tier, and approval rate, sorted by most recently active first. This is
 * the list a QA Lead lands on before drilling into one agent via
 * AgentTrustScoreController.
 */
class AgentListController extends Controller
{
    public function index()
    {
        $submissionStats = Submission::query()
            ->selectRaw('agent_id, count(*) as total_submissions, max(submitted_at) as last_submitted_at')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as approved_count', [SubmissionStatus::Approved->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as rejected_count', [SubmissionStatus::Rejected->value])
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        $trustAverages = SubmissionTrustScore::query()
            ->join('submissions', 'submissions.id', '=', 'submission_trust_scores.submission_id')
            ->selectRaw('submissions.agent_id as agent_id, avg(submission_trust_scores.total_score) as avg_score')
            ->groupBy('submissions.agent_id')
            ->pluck('avg_score', 'agent_id');

        $agents = $submissionStats
            ->map(function (object $row) use ($trustAverages) {
                $overallTrustScore = $trustAverages->has($row->agent_id)
                    ? round((float) $trustAverages->get($row->agent_id), 2)
                    : null;

                $decidedCount = $row->approved_count + $row->rejected_count;

                return [
                    'agent_id' => $row->agent_id,
                    'overall_trust_score' => $overallTrustScore,
                    'tier' => TrustTier::forScore($overallTrustScore),
                    'approval_rate' => $decidedCount > 0 ? round(($row->approved_count / $decidedCount) * 100, 2) : 100.0,
                    'total_submissions' => (int) $row->total_submissions,
                    'last_submitted_at' => Carbon::parse($row->last_submitted_at)->toIso8601String(),
                ];
            })
            ->sortByDesc('last_submitted_at')
            ->values();

        return response()->json(['data' => $agents]);
    }
}
