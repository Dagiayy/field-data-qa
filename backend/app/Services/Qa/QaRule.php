<?php

namespace App\Services\Qa;

use App\Models\Submission;

interface QaRule
{
    /**
     * @return list<array{rule_name: string, result: \App\Enums\QaFlagResult, severity: string, detail: array}>
     */
    public function evaluate(Submission $submission): array;
}
