<?php

namespace Tests\Feature\Qa;

use App\Models\Submission;
use App\Services\Qa\Rules\SurveyDurationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class SurveyDurationRuleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    private function makeTimedSubmission(int $durationSeconds, ?int $minSeconds, ?int $maxSeconds): Submission
    {
        $quest = $this->makeQuest('QST-DUR-'.uniqid());
        $quest->update([
            'expected_duration_min_seconds' => $minSeconds,
            'expected_duration_max_seconds' => $maxSeconds,
        ]);
        $outlet = $this->makeOutlet('OUT-DUR-'.uniqid());

        $start = Carbon::parse('2026-07-24T13:10:00+00:00');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-DUR', 'q1', 'yes', $start);
        $submission->update([
            'survey_start_at' => $start,
            'survey_end_at' => $start->copy()->addSeconds($durationSeconds),
        ]);

        return $submission->fresh(['quest']);
    }

    public function test_duration_within_range_is_not_flagged(): void
    {
        // 270 real seconds forward from start to end — a genuinely later
        // end time, the normal case this bug (Carbon's signed diffInSeconds
        // computed backwards) misclassified as impossibly fast.
        $submission = $this->makeTimedSubmission(270, 60, 600);

        $flags = (new SurveyDurationRule)->evaluate($submission);

        $this->assertCount(0, $flags);
    }

    public function test_duration_shorter_than_minimum_fails_as_too_fast(): void
    {
        $submission = $this->makeTimedSubmission(30, 60, 600);

        $flags = (new SurveyDurationRule)->evaluate($submission);

        $this->assertCount(1, $flags);
        $this->assertSame('survey_duration_outside_expected_range', $flags[0]['rule_name']);
        $this->assertSame('fail', $flags[0]['result']->value);
        $this->assertSame('too_fast', $flags[0]['detail']['direction']);
        $this->assertEquals(30, $flags[0]['detail']['actual_duration_seconds']);
    }

    public function test_duration_longer_than_maximum_flags_as_too_slow(): void
    {
        $submission = $this->makeTimedSubmission(900, 60, 600);

        $flags = (new SurveyDurationRule)->evaluate($submission);

        $this->assertCount(1, $flags);
        $this->assertSame('flag', $flags[0]['result']->value);
        $this->assertSame('too_slow', $flags[0]['detail']['direction']);
    }

    public function test_no_duration_baseline_configured_is_flagged_not_silently_skipped(): void
    {
        $submission = $this->makeTimedSubmission(270, null, null);

        $flags = (new SurveyDurationRule)->evaluate($submission);

        $this->assertCount(1, $flags);
        $this->assertSame('no_duration_baseline_configured', $flags[0]['rule_name']);
        $this->assertSame('flag', $flags[0]['result']->value);
        $this->assertSame('low', $flags[0]['severity']);
    }
}
