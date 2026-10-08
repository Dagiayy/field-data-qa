<?php

namespace App\Services\Qa;

use App\Enums\QaReviewDecision;
use App\Models\QaReview;
use Illuminate\Support\Collection;

/**
 * Every rejection reason an agent has ever received, across every one of
 * their submissions — the shared aggregation behind both
 * AgentRejectionFeedbackController (the detailed feed) and
 * AgentTrustScoreController (the grouped-by-reason-code breakdown).
 */
class AgentRejectionAggregator
{
    /**
     * @return Collection<int, array{submission_id: string, quest_id: ?string, quest_title: ?string, reason_code: ?string, reason_label: ?string, note: ?string, reviewed_at: ?string}>
     */
    public function rejectionsFor(string $agentId): Collection
    {
        return QaReview::query()
            ->where('decision', QaReviewDecision::Reject->value)
            ->whereHas('submission', fn ($q) => $q->where('agent_id', $agentId))
            ->with(['submission.quest', 'rejectionReason'])
            ->orderByDesc('reviewed_at')
            ->get()
            ->map(fn (QaReview $review) => [
                'submission_id' => $review->submission_id,
                'quest_id' => $review->submission->quest?->form_code,
                'quest_title' => $review->submission->quest?->title,
                'reason_code' => $review->rejectionReason?->code,
                'reason_label' => $review->rejectionReason?->label,
                'note' => $review->note,
                'reviewed_at' => optional($review->reviewed_at)->toIso8601String(),
            ]);
    }

    /**
     * @param  Collection<int, array{reason_code: ?string, reason_label: ?string}>  $rejections
     * @return Collection<int, array{reason_code: ?string, label: ?string, count: int}>
     */
    public function breakdown(Collection $rejections): Collection
    {
        return $rejections
            ->groupBy('reason_code')
            ->map(fn ($group, $code) => [
                'reason_code' => $code,
                'label' => $group->first()['reason_label'],
                'count' => $group->count(),
            ])
            ->values();
    }
}
