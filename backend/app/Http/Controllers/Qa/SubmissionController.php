<?php

namespace App\Http\Controllers\Qa;

use App\Enums\QaReviewDecision;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewSubmissionRequest;
use App\Models\QaReview;
use App\Models\RejectionReason;
use App\Models\Submission;
use App\Models\SubmissionTrustScore;
use App\Services\Payments\PaymentStatusDeriver;
use App\Services\Trust\TrustScoreCalculator;
use App\Support\QaPresenter;

class SubmissionController extends Controller
{
    public function __construct(
        private QaPresenter $presenter,
        private TrustScoreCalculator $trustScoreCalculator,
    ) {
    }

    public function show(string $submission)
    {
        $submission = Submission::with([
            'quest',
            'outlet.baselines',
            'answers',
            'media.matchedBaseline',
            'prices',
            'qaFlags',
            'qaReviews.reviewer',
            'qaReviews.rejectionReason',
            'paymentStatus',
            'trustScore',
        ])->findOrFail($submission);

        $agentAverage = SubmissionTrustScore::query()
            ->join('submissions', 'submissions.id', '=', 'submission_trust_scores.submission_id')
            ->where('submissions.agent_id', $submission->agent_id)
            ->avg('submission_trust_scores.total_score');

        return response()->json($this->presenter->submissionDetail(
            $submission,
            $agentAverage !== null ? (float) $agentAverage : null
        ));
    }

    public function review(ReviewSubmissionRequest $request, string $submission, PaymentStatusDeriver $paymentDeriver)
    {
        $submission = Submission::findOrFail($submission);
        $data = $request->validated();

        $note = $data['note'] ?? null;
        if (! empty($data['backcheck_outcome'])) {
            $outcomeLabel = $data['backcheck_outcome'] === 'confirmed' ? 'Confirmed' : 'Not Confirmed';
            $note = trim(($note ? $note.' ' : '')."(Backcheck Outcome: {$outcomeLabel})");
        }

        $reasonId = null;
        if (! empty($data['reason_code'])) {
            $reasonId = RejectionReason::where('code', $data['reason_code'])->value('id');
        }

        $decision = QaReviewDecision::from($data['decision']);

        $review = QaReview::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $request->user()?->id,
            'decision' => $decision,
            'reason_code' => $reasonId,
            'note' => $note,
            'backcheck_outcome' => $data['backcheck_outcome'] ?? null,
            'reviewed_at' => now(),
        ]);

        $submission->status = $this->statusForDecision($decision);
        $submission->save();

        $review->setRelation('submission', $submission);
        $paymentDeriver->deriveFrom($review);

        // audit_confirmation is the one Trust Score dimension driven by
        // Layer 2 human review rather than Layer 1 automated flags, so the
        // composite needs recomputing every time a new review lands —
        // not just once at ingestion. $submission has no qaReviews loaded
        // yet here, so compute() will fetch them fresh, including the one
        // just created above.
        $score = $this->trustScoreCalculator->compute($submission);
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

        return response()->json([
            'success' => true,
            'submission' => [
                'id' => $submission->id,
                'status' => $submission->status->toApiValue(),
            ],
        ]);
    }

    private function statusForDecision(QaReviewDecision $decision): SubmissionStatus
    {
        return match ($decision) {
            QaReviewDecision::Approve => SubmissionStatus::Approved,
            QaReviewDecision::Reject => SubmissionStatus::Rejected,
            QaReviewDecision::FlagBackcheck => SubmissionStatus::Backcheck,
            QaReviewDecision::SendBack => SubmissionStatus::SentBack,
        };
    }
}
