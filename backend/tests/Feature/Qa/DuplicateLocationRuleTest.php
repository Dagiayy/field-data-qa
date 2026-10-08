<?php

namespace Tests\Feature\Qa;

use App\Models\Media;
use App\Services\Qa\Rules\DuplicateLocationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class DuplicateLocationRuleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    private function attachMedia(\App\Models\Submission $submission, float $lat, float $lng): Media
    {
        return Media::create([
            'media_ref' => 'media_'.$submission->id,
            'submission_id' => $submission->id,
            'question_id' => 'q_photo',
            'media_type' => 'image',
            'file_path' => 'https://picsum.photos/seed/test/800/600',
            'captured_at' => now(),
            'gps_at_capture_lat' => $lat,
            'gps_at_capture_lng' => $lng,
        ]);
    }

    public function test_does_not_flag_repeat_visits_to_the_same_outlet(): void
    {
        $quest = $this->makeQuest('QST-DUP-001');
        $outlet = $this->makeOutlet('OUT-DUP-001');

        $lastWeek = $this->makeSubmission($quest, $outlet, 'AGT-DUP-1', 'q1', 'yes', now()->subWeek());
        $this->attachMedia($lastWeek, 9.0123456, 38.7123456);

        $thisWeek = $this->makeSubmission($quest, $outlet, 'AGT-DUP-1', 'q1', 'yes', now());
        $this->attachMedia($thisWeek->fresh(['media']), 9.0123456, 38.7123456);

        $flags = (new DuplicateLocationRule)->evaluate($thisWeek->fresh(['media']));

        $this->assertCount(0, $flags);
    }

    public function test_flags_the_same_gps_shared_with_a_different_outlet(): void
    {
        $quest = $this->makeQuest('QST-DUP-002');
        $outletA = $this->makeOutlet('OUT-DUP-002-A');
        $outletB = $this->makeOutlet('OUT-DUP-002-B');

        $submissionA = $this->makeSubmission($quest, $outletA, 'AGT-DUP-2', 'q1', 'yes', now()->subDay());
        $this->attachMedia($submissionA, 9.0223456, 38.7223456);

        $submissionB = $this->makeSubmission($quest, $outletB, 'AGT-DUP-2', 'q1', 'yes', now());
        $this->attachMedia($submissionB->fresh(['media']), 9.0223456, 38.7223456);

        $flags = (new DuplicateLocationRule)->evaluate($submissionB->fresh(['media']));

        $this->assertCount(1, $flags);
        $this->assertSame('duplicate_gps_location', $flags[0]['rule_name']);
    }
}
