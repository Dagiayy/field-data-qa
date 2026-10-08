<?php

namespace App\Services\Pricing;

use App\Models\Submission;

/**
 * Cross-references price-adjacent context that already exists in the
 * flexible answers[] array (trade type, price type, discount confirmation,
 * pack size) but was never surfaced alongside the price itself. Matches by
 * a keyword in question_id rather than an exact hardcoded id, since quest
 * question naming is flexible per the envelope's design and isn't
 * guaranteed to match the docs/ sample payloads' exact ids.
 */
class SubmissionAnswerLookup
{
    public static function byKeyword(Submission $submission, string $keyword): mixed
    {
        $match = $submission->answers->first(
            fn ($answer) => str_contains(strtolower($answer->question_id), $keyword)
        );

        return $match?->value;
    }

    /**
     * Looks up the pack_size for a given row_id inside a repeat_table
     * answer (e.g. q4_pack_sizes: [{row_id, pack_size, available}, ...]).
     */
    public static function packSize(Submission $submission, ?string $rowId): ?string
    {
        if ($rowId === null) {
            return null;
        }

        foreach ($submission->answers as $answer) {
            if ($answer->input_type !== 'repeat_table' || ! is_array($answer->value)) {
                continue;
            }

            foreach ($answer->value as $row) {
                if (is_array($row) && ($row['row_id'] ?? null) === $rowId && isset($row['pack_size'])) {
                    return (string) $row['pack_size'];
                }
            }
        }

        return null;
    }
}
