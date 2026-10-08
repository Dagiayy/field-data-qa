<?php

namespace App\Http\Controllers\Qa;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\SubmissionTrustScore;
use App\Support\QaPresenter;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function __construct(private QaPresenter $presenter)
    {
    }

    public function index(Request $request)
    {
        $perPage = 15;

        $query = Submission::query()
            ->with(['quest', 'outlet', 'qaFlags', 'paymentStatus', 'trustScore', 'qaReviews.reviewer', 'qaReviews.rejectionReason'])
            ->orderBy('submitted_at');

        if ($status = $request->query('status')) {
            $query->where('status', SubmissionStatus::fromApiValue($status)->value);
        } else {
            $query->whereIn('status', [
                SubmissionStatus::PendingReview->value,
                SubmissionStatus::Backcheck->value,
                SubmissionStatus::SentBack->value,
            ]);
        }

        if ($questId = $request->query('quest_id')) {
            $query->whereHas('quest', fn ($q) => $q->where('form_code', $questId));
        }

        if ($outletId = $request->query('outlet_id')) {
            $query->whereHas('outlet', fn ($q) => $q->where('code', $outletId));
        }

        if ($agentId = $request->query('agent_id')) {
            $query->where('agent_id', 'ilike', "%{$agentId}%");
        }

        if ($request->boolean('has_flags')) {
            $query->whereHas('qaFlags', fn ($q) => $q->whereIn('result', ['flag', 'fail']));
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', (int) $request->query('page', 1));

        $agentIds = collect($paginator->items())->pluck('agent_id')->unique()->values();

        // One grouped query for the whole page rather than one per row —
        // the queue's Trust Score column shows each agent's overall
        // standing, not just this one submission's score.
        $agentAverages = SubmissionTrustScore::query()
            ->join('submissions', 'submissions.id', '=', 'submission_trust_scores.submission_id')
            ->whereIn('submissions.agent_id', $agentIds)
            ->selectRaw('submissions.agent_id as agent_id, avg(submission_trust_scores.total_score) as avg_score')
            ->groupBy('submissions.agent_id')
            ->pluck('avg_score', 'agent_id');

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (Submission $s) => $this->presenter->queueItem(
                    $s,
                    $agentAverages->has($s->agent_id) ? (float) $agentAverages->get($s->agent_id) : null
                ))
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
