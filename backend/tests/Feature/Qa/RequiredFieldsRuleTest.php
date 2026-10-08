<?php

namespace Tests\Feature\Qa;

use App\Services\Qa\Rules\RequiredFieldsRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class RequiredFieldsRuleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    public function test_flags_no_required_fields_configured_when_quest_has_none_set(): void
    {
        $quest = $this->makeQuest('QST-REQFIELDS-001'); // required_question_ids left null
        $outlet = $this->makeOutlet('OUT-REQFIELDS-001');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-R', 'q1', 'yes', now());

        $flags = (new RequiredFieldsRule)->evaluate($submission->fresh(['quest', 'answers']));

        $this->assertCount(1, $flags);
        $this->assertSame('no_required_fields_configured', $flags[0]['rule_name']);
        $this->assertSame('flag', $flags[0]['result']->value);
        $this->assertSame('low', $flags[0]['severity']);
    }

    public function test_does_not_flag_when_all_configured_required_fields_are_present(): void
    {
        $quest = $this->makeQuest('QST-REQFIELDS-002');
        $quest->update(['required_question_ids' => ['q1']]);
        $outlet = $this->makeOutlet('OUT-REQFIELDS-002');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-S', 'q1', 'yes', now());

        $flags = (new RequiredFieldsRule)->evaluate($submission->fresh(['quest', 'answers']));

        $this->assertCount(0, $flags);
    }

    public function test_flags_missing_required_fields_when_configured_and_absent(): void
    {
        $quest = $this->makeQuest('QST-REQFIELDS-003');
        $quest->update(['required_question_ids' => ['q1', 'q_never_answered']]);
        $outlet = $this->makeOutlet('OUT-REQFIELDS-003');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-T', 'q1', 'yes', now());

        $flags = (new RequiredFieldsRule)->evaluate($submission->fresh(['quest', 'answers']));

        $this->assertCount(1, $flags);
        $this->assertSame('missing_required_fields', $flags[0]['rule_name']);
        $this->assertSame('fail', $flags[0]['result']->value);
        $this->assertSame(['q_never_answered'], $flags[0]['detail']['missing_question_ids']);
    }
}
