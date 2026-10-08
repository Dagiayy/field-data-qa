<?php

namespace Tests\Feature\Qa;

use App\Services\Qa\Rules\TimeSequenceRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class TimeSequenceRuleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    public function test_flags_no_quest_acceptance_recorded_when_accepted_at_is_missing(): void
    {
        $quest = $this->makeQuest('QST-TIMESEQ-001');
        $outlet = $this->makeOutlet('OUT-TIMESEQ-001');
        $start = Carbon::parse('2026-07-24T07:05:00+00:00');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-X', 'q1', 'yes', $start);
        // makeSubmission leaves quest_accepted_at null by default.

        $flags = (new TimeSequenceRule)->evaluate($submission->fresh());

        $this->assertCount(1, $flags);
        $this->assertSame('no_quest_acceptance_recorded', $flags[0]['rule_name']);
        $this->assertSame('flag', $flags[0]['result']->value);
        $this->assertSame('low', $flags[0]['severity']);
    }

    public function test_does_not_flag_no_quest_acceptance_when_accepted_at_is_present_and_normal(): void
    {
        $quest = $this->makeQuest('QST-TIMESEQ-002');
        $outlet = $this->makeOutlet('OUT-TIMESEQ-002');
        $start = Carbon::parse('2026-07-24T07:05:00+00:00');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-Y', 'q1', 'yes', $start);
        $submission->update(['quest_accepted_at' => $start->copy()->subMinutes(2)]);

        $flags = (new TimeSequenceRule)->evaluate($submission->fresh());

        $this->assertCount(0, $flags);
    }

    public function test_flags_excessive_accept_to_start_delay_when_accepted_at_is_present_but_far_before_start(): void
    {
        $quest = $this->makeQuest('QST-TIMESEQ-003');
        $outlet = $this->makeOutlet('OUT-TIMESEQ-003');
        $start = Carbon::parse('2026-07-24T07:05:00+00:00');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-Z', 'q1', 'yes', $start);
        $submission->update(['quest_accepted_at' => $start->copy()->subHours(5)]);

        $flags = (new TimeSequenceRule)->evaluate($submission->fresh());

        $this->assertCount(1, $flags);
        $this->assertSame('excessive_accept_to_start_delay', $flags[0]['rule_name']);
    }

    public function test_flags_sequence_inconsistency_when_start_is_after_end(): void
    {
        $quest = $this->makeQuest('QST-TIMESEQ-004');
        $outlet = $this->makeOutlet('OUT-TIMESEQ-004');
        $start = Carbon::parse('2026-07-24T07:20:00+00:00');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-AA', 'q1', 'yes', $start);
        $submission->update([
            'survey_start_at' => $start,
            'survey_end_at' => $start->copy()->subMinutes(10),
        ]);

        $flags = (new TimeSequenceRule)->evaluate($submission->fresh());

        $this->assertTrue(collect($flags)->contains(fn ($f) => $f['rule_name'] === 'time_sequence_inconsistent' && $f['detail']['check'] === 'started_after_finished'));
    }
}
