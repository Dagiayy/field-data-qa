<?php

namespace App\Services\Qa\Rules;

use App\Enums\QaFlagResult;
use App\Models\Submission;
use App\Services\Qa\QaRule;

/**
 * "Completeness of Submitted Forms" — checks the agent's answers against
 * the quest's own configured required_question_ids. A quest with NO
 * required fields configured at all is an admin/setup gap (nobody's
 * defined what "complete" even means for this quest yet), not proof the
 * agent submitted a complete form — flagged the same way a missing
 * duration/GPS baseline is elsewhere (see SurveyDurationRule,
 * BaselineLocationRule), rather than silently skipped and defaulting
 * completeness to a rubber-stamped 100.
 */
class RequiredFieldsRule implements QaRule
{
    public function evaluate(Submission $submission): array
    {
        $required = $submission->quest?->required_question_ids ?? [];
        if (empty($required)) {
            return [[
                'rule_name' => 'no_required_fields_configured',
                'result' => QaFlagResult::Flag,
                'severity' => 'low',
                'detail' => [
                    'quest_id' => $submission->quest?->form_code,
                ],
            ]];
        }

        $present = $submission->answers->pluck('question_id')->unique()->all();
        $missing = array_values(array_diff($required, $present));

        if (empty($missing)) {
            return [];
        }

        return [[
            'rule_name' => 'missing_required_fields',
            'result' => QaFlagResult::Fail,
            'severity' => 'medium',
            'detail' => [
                'missing_question_ids' => $missing,
            ],
        ]];
    }
}
