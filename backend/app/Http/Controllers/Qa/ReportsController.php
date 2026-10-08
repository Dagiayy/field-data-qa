<?php

namespace App\Http\Controllers\Qa;

use App\Enums\PaymentWalletState;
use App\Enums\QaFlagResult;
use App\Enums\QaReviewDecision;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentStatus;
use App\Models\QaFlag;
use App\Models\QaReview;
use App\Models\Submission;
use App\Models\SubmissionTrustScore;
use App\Services\Trust\TrustTier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Aggregate operational/quality metrics for the QA Lead Dashboard
 * (frontend/src/pages/QAOverview.tsx). Every number here is a live database
 * aggregate over Submission/QaReview/QaFlag/SubmissionTrustScore/
 * PaymentStatus, scoped to an optional [date_from, date_to] submitted_at
 * window — nothing is cached or precomputed, so it can never go stale
 * independently of the underlying data the way a persisted snapshot would.
 */
class ReportsController extends Controller
{
    private const QUEUE_STATUSES = [
        SubmissionStatus::PendingReview->value,
        SubmissionStatus::Backcheck->value,
        SubmissionStatus::SentBack->value,
    ];

    private const DECIDED_STATUSES = [
        SubmissionStatus::Approved->value,
        SubmissionStatus::Rejected->value,
        SubmissionStatus::AutoRejected->value,
    ];

    private const TIER_ORDER = ['Gold', 'Silver', 'Bronze', 'Flagged'];

    public function overview(Request $request)
    {
        $dateFrom = $request->query('date_from') ? Carbon::parse($request->query('date_from'))->startOfDay() : null;
        $dateTo = $request->query('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : null;

        $submissions = Submission::query()
            ->when($dateFrom, fn ($q) => $q->where('submitted_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('submitted_at', '<=', $dateTo));

        $queueByStatus = (clone $submissions)
            ->whereIn('status', self::QUEUE_STATUSES)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $oldestPending = (clone $submissions)
            ->whereIn('status', self::QUEUE_STATUSES)
            ->orderBy('submitted_at')
            ->value('submitted_at');

        $decidedCounts = (clone $submissions)
            ->whereIn('status', self::DECIDED_STATUSES)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $approvedCount = (int) ($decidedCounts[SubmissionStatus::Approved->value] ?? 0);
        $rejectedCount = (int) ($decidedCounts[SubmissionStatus::Rejected->value] ?? 0)
            + (int) ($decidedCounts[SubmissionStatus::AutoRejected->value] ?? 0);
        $backcheckCount = (int) ($queueByStatus[SubmissionStatus::Backcheck->value] ?? 0);
        $decidedTotal = $approvedCount + $rejectedCount;

        // Decision latency is computed in PHP rather than raw SQL date-diff
        // (EXTRACT(EPOCH FROM ...) is Postgres-only and doesn't translate to
        // the sqlite connection the test suite runs against).
        $decidedLatencyMinutes = QaReview::query()
            ->join('submissions', 'submissions.id', '=', 'qa_reviews.submission_id')
            ->when($dateFrom, fn ($q) => $q->where('submissions.submitted_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('submissions.submitted_at', '<=', $dateTo))
            ->get(['qa_reviews.reviewed_at', 'submissions.submitted_at'])
            ->map(fn ($row) => Carbon::parse($row->submitted_at)->diffInSeconds(Carbon::parse($row->reviewed_at)) / 60)
            ->avg();

        $avgTrustScore = SubmissionTrustScore::query()
            ->join('submissions', 'submissions.id', '=', 'submission_trust_scores.submission_id')
            ->when($dateFrom, fn ($q) => $q->where('submissions.submitted_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('submissions.submitted_at', '<=', $dateTo))
            ->avg('submission_trust_scores.total_score');

        $tierCounts = SubmissionTrustScore::query()
            ->join('submissions', 'submissions.id', '=', 'submission_trust_scores.submission_id')
            ->when($dateFrom, fn ($q) => $q->where('submissions.submitted_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('submissions.submitted_at', '<=', $dateTo))
            ->pluck('submission_trust_scores.total_score')
            ->map(fn ($score) => TrustTier::forScore((float) $score))
            ->countBy();

        $tierDistribution = collect(self::TIER_ORDER)->map(fn ($tier) => [
            'tier' => $tier,
            'count' => (int) ($tierCounts[$tier] ?? 0),
        ]);

        $approvalByQuest = (clone $submissions)
            ->join('quests', 'quests.id', '=', 'submissions.quest_id')
            ->whereIn('submissions.status', self::DECIDED_STATUSES)
            ->selectRaw(
                'quests.title as quest_title, count(*) as total, sum(case when submissions.status = ? then 1 else 0 end) as approved',
                [SubmissionStatus::Approved->value]
            )
            ->groupBy('quests.title')
            ->get()
            ->map(fn ($row) => [
                'quest_title' => $row->quest_title,
                'approval_rate' => $row->total > 0 ? round(($row->approved / $row->total) * 100, 1) : 0.0,
                'total' => (int) $row->total,
            ]);

        $approvalByCity = (clone $submissions)
            ->join('outlets', 'outlets.id', '=', 'submissions.outlet_id')
            ->whereIn('submissions.status', self::DECIDED_STATUSES)
            ->selectRaw(
                'outlets.city as city, count(*) as total, sum(case when submissions.status = ? then 1 else 0 end) as approved',
                [SubmissionStatus::Approved->value]
            )
            ->groupBy('outlets.city')
            ->get()
            ->map(fn ($row) => [
                'city' => $row->city,
                'approval_rate' => $row->total > 0 ? round(($row->approved / $row->total) * 100, 1) : 0.0,
                'total' => (int) $row->total,
            ]);

        $reviewerThroughput = QaReview::query()
            ->join('submissions', 'submissions.id', '=', 'qa_reviews.submission_id')
            ->join('users', 'users.id', '=', 'qa_reviews.reviewer_id')
            ->when($dateFrom, fn ($q) => $q->where('submissions.submitted_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('submissions.submitted_at', '<=', $dateTo))
            ->get(['users.name as reviewer_name', 'qa_reviews.reviewed_at', 'submissions.submitted_at'])
            ->groupBy('reviewer_name')
            ->map(fn ($rows, $reviewerName) => [
                'reviewer_name' => $reviewerName,
                'reviewed_count' => $rows->count(),
                'avg_decision_time_minutes' => round(
                    $rows->avg(fn ($row) => Carbon::parse($row->submitted_at)->diffInSeconds(Carbon::parse($row->reviewed_at)) / 60),
                    1
                ),
            ])
            ->values();

        $topFlags = QaFlag::query()
            ->join('submissions', 'submissions.id', '=', 'qa_flags.submission_id')
            ->when($dateFrom, fn ($q) => $q->where('submissions.submitted_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('submissions.submitted_at', '<=', $dateTo))
            ->where('qa_flags.result', '!=', QaFlagResult::Pass->value)
            ->selectRaw('qa_flags.rule_name, qa_flags.result, qa_flags.severity, count(*) as count')
            ->groupBy('qa_flags.rule_name', 'qa_flags.result', 'qa_flags.severity')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'rule_name' => $row->rule_name,
                'result' => $row->result instanceof QaFlagResult ? $row->result->value : $row->result,
                'severity' => $row->severity,
                'count' => (int) $row->count,
            ]);

        // qa_reviews.reason_code is a foreignId into rejection_reasons.id
        // (misleadingly named after the human-readable code it points to) —
        // join on id, and surface rejection_reasons.code as the string the
        // dashboard should actually display.
        $rejectionBreakdown = QaReview::query()
            ->join('submissions', 'submissions.id', '=', 'qa_reviews.submission_id')
            ->leftJoin('rejection_reasons', 'rejection_reasons.id', '=', 'qa_reviews.reason_code')
            ->where('qa_reviews.decision', QaReviewDecision::Reject->value)
            ->when($dateFrom, fn ($q) => $q->where('submissions.submitted_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('submissions.submitted_at', '<=', $dateTo))
            ->selectRaw('rejection_reasons.code as reason_code, rejection_reasons.label, count(*) as count')
            ->groupBy('rejection_reasons.code', 'rejection_reasons.label')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'reason_code' => $row->reason_code ?? 'unspecified',
                'label' => $row->label ?? 'Unspecified',
                'count' => (int) $row->count,
            ]);

        $paymentFunnel = PaymentStatus::query()
            ->join('submissions', 'submissions.id', '=', 'payment_statuses.submission_id')
            ->when($dateFrom, fn ($q) => $q->where('submissions.submitted_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('submissions.submitted_at', '<=', $dateTo))
            ->selectRaw('payment_statuses.wallet_state, payment_statuses.currency, count(*) as count, coalesce(sum(payment_statuses.amount), 0) as total_amount')
            ->groupBy('payment_statuses.wallet_state', 'payment_statuses.currency')
            ->get()
            ->map(fn ($row) => [
                'wallet_state' => $row->wallet_state instanceof PaymentWalletState ? $row->wallet_state->value : $row->wallet_state,
                'currency' => $row->currency,
                'count' => (int) $row->count,
                'total_amount' => (float) $row->total_amount,
            ]);

        return response()->json([
            'queue_depth' => (int) $queueByStatus->sum(),
            'queue_by_status' => [
                'pending_review' => (int) ($queueByStatus[SubmissionStatus::PendingReview->value] ?? 0),
                'backcheck' => (int) ($queueByStatus[SubmissionStatus::Backcheck->value] ?? 0),
                'sent_back' => (int) ($queueByStatus[SubmissionStatus::SentBack->value] ?? 0),
            ],
            'oldest_pending_age_hours' => $oldestPending ? round(Carbon::parse($oldestPending)->diffInHours(now(), true), 1) : null,
            'avg_time_in_queue_minutes' => $decidedLatencyMinutes !== null ? round((float) $decidedLatencyMinutes, 1) : null,
            'avg_trust_score' => $avgTrustScore !== null ? round((float) $avgTrustScore, 1) : null,
            'approval_rate' => $decidedTotal > 0 ? round(($approvedCount / $decidedTotal) * 100, 1) : null,
            'rejection_rate' => $decidedTotal > 0 ? round(($rejectedCount / $decidedTotal) * 100, 1) : null,
            'backcheck_rate' => ($decidedTotal + $backcheckCount) > 0
                ? round(($backcheckCount / ($decidedTotal + $backcheckCount)) * 100, 1)
                : null,
            'trust_tier_distribution' => $tierDistribution,
            'approval_rate_by_quest' => $approvalByQuest,
            'approval_rate_by_city' => $approvalByCity,
            'reviewer_throughput' => $reviewerThroughput,
            'top_qa_flags' => $topFlags,
            'rejection_breakdown' => $rejectionBreakdown,
            'payment_funnel' => $paymentFunnel,
        ]);
    }
}
