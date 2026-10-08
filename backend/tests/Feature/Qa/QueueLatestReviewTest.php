<?php

namespace Tests\Feature\Qa;

use App\Enums\QaReviewDecision;
use App\Enums\SubmissionStatus;
use App\Models\QaReview;
use App\Models\RejectionReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class QueueLatestReviewTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    public function test_rejected_queue_item_surfaces_the_reviewers_reason_and_note(): void
    {
        $quest = $this->makeQuest('QST-QLR-001');
        $outlet = $this->makeOutlet('OUT-QLR-001');
        $reason = RejectionReason::create(['code' => 'PRICE_OUTLIER', 'label' => 'Unusual Price Entry / Typo', 'active' => true]);

        $submission = $this->makeSubmission($quest, $outlet, 'AGT-QLR', 'q1', 'yes', now());
        $submission->update(['status' => SubmissionStatus::Rejected]);

        QaReview::create([
            'submission_id' => $submission->id,
            'decision' => QaReviewDecision::Reject,
            'reason_code' => $reason->id,
            'note' => 'Price is way outside the expected range.',
            'reviewed_at' => now(),
        ]);

        $response = $this->getJson('/api/qa/queue?status=rejected');

        $response->assertOk();
        $item = collect($response->json('data'))->firstWhere('id', $submission->id);

        $this->assertNotNull($item);
        $this->assertSame('reject', $item['latest_review']['decision']);
        $this->assertSame('PRICE_OUTLIER', $item['latest_review']['reason_code']);
        $this->assertSame('Unusual Price Entry / Typo', $item['latest_review']['reason_label']);
        $this->assertSame('Price is way outside the expected range.', $item['latest_review']['note']);
    }

    public function test_pending_queue_item_has_no_latest_review(): void
    {
        $quest = $this->makeQuest('QST-QLR-002');
        $outlet = $this->makeOutlet('OUT-QLR-002');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-QLR-2', 'q1', 'yes', now());

        $response = $this->getJson('/api/qa/queue?status=pending');

        $response->assertOk();
        $item = collect($response->json('data'))->firstWhere('id', $submission->id);

        $this->assertNotNull($item);
        $this->assertNull($item['latest_review']);
    }
}
