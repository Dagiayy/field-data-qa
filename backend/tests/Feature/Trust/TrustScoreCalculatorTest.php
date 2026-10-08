<?php

namespace Tests\Feature\Trust;

use App\Enums\QaFlagResult;
use App\Enums\QaReviewDecision;
use App\Models\Media;
use App\Models\QaFlag;
use App\Models\QaReview;
use App\Services\Trust\TrustScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class TrustScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    public function test_clean_unreviewed_submission_scores_100_except_pending_audit_confirmation(): void
    {
        $quest = $this->makeQuest('QST-TRUST-001');
        $outlet = $this->makeOutlet('OUT-TRUST-001');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-CLEAN', 'q1', 'yes', now());

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(100.0, $score['gps']);
        $this->assertSame(100.0, $score['photo']);
        $this->assertSame(100.0, $score['time']);
        $this->assertSame(100.0, $score['completeness']);
        // No human review has happened yet — nothing to confirm.
        $this->assertSame(75.0, $score['audit_confirmation']);
        $this->assertSame(TrustScoreCalculator::DEFAULT_WEIGHTS, $score['weights']);
    }

    public function test_fail_flag_scores_lower_than_a_soft_flag_in_the_same_dimension(): void
    {
        $quest = $this->makeQuest('QST-TRUST-002');
        $outlet = $this->makeOutlet('OUT-TRUST-002');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-A', 'q1', 'yes', now());
        $this->makeFlag($submission, 'geofence_check', QaFlagResult::Fail);

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(35.0, $score['gps']);
        $this->assertSame(100.0, $score['photo']);
    }

    public function test_gps_flag_within_40m_is_not_penalized_for_trust_score(): void
    {
        $quest = $this->makeQuest('QST-TRUST-007');
        $outlet = $this->makeOutlet('OUT-TRUST-007');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-F', 'q1', 'yes', now());

        // A stricter automated rule (the baseline's own tighter registered
        // radius) still raised this as a fail — but 35m is well within a
        // real shop's physical footprint, a single GPS point can't be that
        // precise, so it shouldn't drag the Trust Score down.
        QaFlag::create([
            'submission_id' => $submission->id,
            'rule_name' => 'possible_wrong_location',
            'result' => QaFlagResult::Fail,
            'severity' => 'high',
            'detail' => ['distance_m' => 35.0, 'allowed_radius_m' => 20],
        ]);

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(100.0, $score['gps']);
    }

    public function test_gps_flag_beyond_40m_still_fails_trust_score(): void
    {
        $quest = $this->makeQuest('QST-TRUST-008');
        $outlet = $this->makeOutlet('OUT-TRUST-008');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-G', 'q1', 'yes', now());

        QaFlag::create([
            'submission_id' => $submission->id,
            'rule_name' => 'geofence_check',
            'result' => QaFlagResult::Fail,
            'severity' => 'high',
            'detail' => ['distance_m' => 250.0, 'allowed_radius_m' => 150],
        ]);

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(35.0, $score['gps']);
    }

    public function test_duplicate_gps_location_flag_is_never_overridden_by_the_distance_radius(): void
    {
        $quest = $this->makeQuest('QST-TRUST-009');
        $outlet = $this->makeOutlet('OUT-TRUST-009');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-H', 'q1', 'yes', now());

        // duplicate_gps_location fires precisely BECAUSE the distance is
        // tiny (same spot suspiciously reused) — small distance is the bad
        // signal here, the opposite of possible_wrong_location/
        // geofence_check, so the 40m "close enough" override must never
        // apply to it regardless of how small its own distance_m is.
        QaFlag::create([
            'submission_id' => $submission->id,
            'rule_name' => 'duplicate_gps_location',
            'result' => QaFlagResult::Flag,
            'severity' => 'medium',
            'detail' => ['distance_m' => 1.2],
        ]);

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(75.0, $score['gps']);
    }

    public function test_poor_gps_accuracy_flag_is_never_overridden_by_the_distance_radius(): void
    {
        $quest = $this->makeQuest('QST-TRUST-010');
        $outlet = $this->makeOutlet('OUT-TRUST-010');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-I', 'q1', 'yes', now());

        QaFlag::create([
            'submission_id' => $submission->id,
            'rule_name' => 'poor_gps_accuracy',
            'result' => QaFlagResult::Flag,
            'severity' => 'medium',
            'detail' => ['reported_accuracy_m' => 80.0, 'max_acceptable_accuracy_m' => 50],
        ]);

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(75.0, $score['gps']);
    }

    public function test_gps_score_is_not_a_rubber_stamped_pass_when_outlet_has_no_baseline(): void
    {
        $quest = $this->makeQuest('QST-TRUST-017');
        $outlet = $this->makeOutlet('OUT-TRUST-017');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-U', 'q1', 'yes', now());

        // BaselineLocationRule raises this when the outlet has zero
        // registered baselines — the GPS-vs-baseline distance check never
        // actually ran, so this must score below an ordinary flag (75):
        // "nothing to check against" is worse than "checked and borderline".
        $this->makeFlag($submission, 'no_outlet_baseline_configured', QaFlagResult::Flag);

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(40.0, $score['gps']);
    }

    public function test_photo_score_deducts_when_outlet_has_no_baseline_to_compare_against(): void
    {
        $quest = $this->makeQuest('QST-TRUST-018');
        $outlet = $this->makeOutlet('OUT-TRUST-018');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-V', 'q1', 'yes', now());

        Media::create([
            'submission_id' => $submission->id,
            'media_ref' => 'media_001',
            'question_id' => 'q_photo',
            'media_type' => 'image',
            'file_path' => 'https://picsum.photos/seed/trust-018/4032/3024',
            'captured_at' => now(),
        ]);
        $this->makeFlag($submission, 'no_outlet_baseline_configured', QaFlagResult::Flag);

        $score = (new TrustScoreCalculator)->compute($submission->fresh(['media']));

        // 100 - 35 (flat deduction, applied once — this flag isn't any one
        // photo's fault, but every photo went completely unverified) = 65.
        $this->assertSame(65.0, $score['photo']);
    }

    public function test_completeness_score_is_not_a_rubber_stamped_pass_when_quest_has_no_required_fields(): void
    {
        $quest = $this->makeQuest('QST-TRUST-019');
        $outlet = $this->makeOutlet('OUT-TRUST-019');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-W', 'q1', 'yes', now());

        // RequiredFieldsRule raises this when the quest has no
        // required_question_ids configured — "complete" was never defined
        // for this quest, so it must score below an ordinary flag: nothing
        // was actually checked, not "checked and borderline".
        $this->makeFlag($submission, 'no_required_fields_configured', QaFlagResult::Flag);

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(40.0, $score['completeness']);
    }

    public function test_missing_required_fields_only_affects_completeness(): void
    {
        $quest = $this->makeQuest('QST-TRUST-005');
        $outlet = $this->makeOutlet('OUT-TRUST-005');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-D', 'q1', 'yes', now());
        $this->makeFlag($submission, 'missing_required_fields', QaFlagResult::Fail);

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(35.0, $score['completeness']);
        $this->assertSame(100.0, $score['gps']);
        $this->assertSame(100.0, $score['photo']);
        $this->assertSame(100.0, $score['time']);
    }

    public function test_audit_confirmation_reflects_the_latest_review_decision(): void
    {
        $quest = $this->makeQuest('QST-TRUST-006');
        $outlet = $this->makeOutlet('OUT-TRUST-006');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-E', 'q1', 'yes', now());

        QaReview::create([
            'submission_id' => $submission->id,
            'decision' => QaReviewDecision::Approve,
            'reviewed_at' => now(),
        ]);

        $score = (new TrustScoreCalculator)->compute($submission->fresh());
        $this->assertSame(100.0, $score['audit_confirmation']);

        QaReview::create([
            'submission_id' => $submission->id,
            'decision' => QaReviewDecision::Reject,
            'reviewed_at' => now()->addMinute(),
        ]);

        $score = (new TrustScoreCalculator)->compute($submission->fresh());
        // "if it failed deduct by 50%" — an explicit half-score, distinct
        // from the generic FAIL_SCORE (35) other dimensions still use.
        $this->assertSame(50.0, $score['audit_confirmation']);
    }

    public function test_photo_score_is_a_graduated_deduction_not_a_flat_tier(): void
    {
        $quest = $this->makeQuest('QST-TRUST-011');
        $outlet = $this->makeOutlet('OUT-TRUST-011');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-J', 'q1', 'yes', now());

        Media::create([
            'submission_id' => $submission->id,
            'media_ref' => 'media_001',
            'question_id' => 'q_photo',
            'media_type' => 'image',
            'file_path' => 'https://picsum.photos/seed/trust-011/4032/3024',
            'captured_at' => now(),
        ]);

        QaFlag::create([
            'submission_id' => $submission->id,
            'rule_name' => 'low_photo_resolution',
            'result' => QaFlagResult::Fail,
            'severity' => 'high',
            'detail' => ['media_ref' => 'media_001'],
        ]);

        $score = (new TrustScoreCalculator)->compute($submission->fresh(['media']));

        // 100 - 40 (fail/high deduction) = 60 — not the old flat FAIL_SCORE
        // of 35, and not a flat 75/100 either: proportional to the one
        // real issue found on the one photo present.
        $this->assertSame(60.0, $score['photo']);
    }

    public function test_photo_score_averages_across_multiple_photos_in_the_submission(): void
    {
        $quest = $this->makeQuest('QST-TRUST-012');
        $outlet = $this->makeOutlet('OUT-TRUST-012');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-K', 'q1', 'yes', now());

        Media::create([
            'submission_id' => $submission->id,
            'media_ref' => 'media_clean',
            'question_id' => 'q_photo_1',
            'media_type' => 'image',
            'file_path' => 'https://picsum.photos/seed/trust-012-clean/4032/3024',
            'captured_at' => now(),
        ]);
        Media::create([
            'submission_id' => $submission->id,
            'media_ref' => 'media_dim',
            'question_id' => 'q_photo_2',
            'media_type' => 'image',
            'file_path' => 'https://picsum.photos/seed/trust-012-dim/4032/3024',
            'captured_at' => now(),
        ]);

        QaFlag::create([
            'submission_id' => $submission->id,
            'rule_name' => 'poor_photo_lighting',
            'result' => QaFlagResult::Flag,
            'severity' => 'medium',
            'detail' => ['media_ref' => 'media_dim'],
        ]);

        $score = (new TrustScoreCalculator)->compute($submission->fresh(['media']));

        // media_clean: 100 (no flags). media_dim: 100 - 12 (flag/medium) = 88.
        // Average = 94 — one flagged photo shouldn't drag the whole
        // submission down to that photo's own score.
        $this->assertSame(94.0, $score['photo']);
    }

    public function test_time_score_deducts_for_chronological_sequence_inconsistency(): void
    {
        $quest = $this->makeQuest('QST-TRUST-013');
        $outlet = $this->makeOutlet('OUT-TRUST-013');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-L', 'q1', 'yes', now());
        $this->makeFlag($submission, 'time_sequence_inconsistent', QaFlagResult::Fail);

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(55.0, $score['time']);
    }

    public function test_time_score_deducts_for_an_unconfigured_duration_baseline(): void
    {
        $quest = $this->makeQuest('QST-TRUST-016');
        $outlet = $this->makeOutlet('OUT-TRUST-016');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-O', 'q1', 'yes', now());

        // SurveyDurationRule raises this whenever a quest has no expected
        // duration range configured yet — the duration magnitude check
        // never actually ran, so this must not score the same as a
        // genuinely-checked-and-clean submission.
        $this->makeFlag($submission, 'no_duration_baseline_configured', QaFlagResult::Flag);

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(70.0, $score['time']);
    }

    public function test_time_score_deducts_when_no_quest_acceptance_was_recorded(): void
    {
        $quest = $this->makeQuest('QST-TRUST-020');
        $outlet = $this->makeOutlet('OUT-TRUST-020');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-X2', 'q1', 'yes', now());

        // TimeSequenceRule raises this whenever no QuestAcceptance record
        // exists — we can't confirm when the agent accepted the quest (or
        // that they did at all), and the dedication bonus below can never
        // apply, so this costs real points rather than just forfeiting the
        // upside.
        $this->makeFlag($submission, 'no_quest_acceptance_recorded', QaFlagResult::Flag);

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(80.0, $score['time']);
    }

    public function test_time_score_stacks_both_unconfigured_deductions_when_neither_baseline_nor_acceptance_exist(): void
    {
        $quest = $this->makeQuest('QST-TRUST-021');
        $outlet = $this->makeOutlet('OUT-TRUST-021');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-X3', 'q1', 'yes', now());

        $this->makeFlag($submission, 'no_duration_baseline_configured', QaFlagResult::Flag);
        $this->makeFlag($submission, 'no_quest_acceptance_recorded', QaFlagResult::Flag);

        $score = (new TrustScoreCalculator)->compute($submission);

        // 100 - 30 (no duration baseline) - 20 (no quest acceptance) = 50.
        $this->assertSame(50.0, $score['time']);
    }

    public function test_time_score_stale_photo_deduction_is_offset_by_dedication_bonus(): void
    {
        $quest = $this->makeQuest('QST-TRUST-014');
        $outlet = $this->makeOutlet('OUT-TRUST-014');
        $submittedAt = now();
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-M', 'q1', 'yes', $submittedAt);
        // Accepted the quest 2 minutes before starting — well inside the
        // 5-minute "dedicated start" window.
        $submission->update(['quest_accepted_at' => $submittedAt->copy()->subMinutes(2)]);

        QaFlag::create([
            'submission_id' => $submission->id,
            'rule_name' => 'timestamp_consistency',
            'result' => QaFlagResult::Fail,
            'severity' => 'high',
            'detail' => ['check' => 'stale_photo'],
        ]);

        $score = (new TrustScoreCalculator)->compute($submission->fresh());

        // 100 - 40 (stale photo) + 12 (dedication bonus) = 72.
        $this->assertSame(72.0, $score['time']);
    }

    public function test_time_score_dedication_bonus_never_pushes_a_clean_record_above_100(): void
    {
        $quest = $this->makeQuest('QST-TRUST-015');
        $outlet = $this->makeOutlet('OUT-TRUST-015');
        $submittedAt = now();
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-N', 'q1', 'yes', $submittedAt);
        $submission->update(['quest_accepted_at' => $submittedAt->copy()->subMinutes(1)]);

        $score = (new TrustScoreCalculator)->compute($submission->fresh());

        $this->assertSame(100.0, $score['time']);
    }

    public function test_uses_the_quests_configured_weights_instead_of_defaults(): void
    {
        $quest = $this->makeQuest('QST-TRUST-003', [
            'gps' => 0.10,
            'photo' => 0.30,
            'completeness' => 0.20,
            'time' => 0.30,
            'audit_confirmation' => 0.10,
        ]);
        $outlet = $this->makeOutlet('OUT-TRUST-003');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-B', 'q1', 'yes', now());
        $this->makeFlag($submission, 'geofence_check', QaFlagResult::Fail); // gps -> 35

        $score = (new TrustScoreCalculator)->compute($submission);

        // total = 0.10*35 + 0.30*100 + 0.20*100 + 0.30*100 + 0.10*75 (no review yet)
        //       = 3.5 + 30 + 20 + 30 + 7.5 = 91.0
        $this->assertEquals(91.0, $score['total']);
    }

    public function test_falls_back_to_default_weights_when_quest_has_none_configured(): void
    {
        $quest = $this->makeQuest('QST-TRUST-004'); // no trust_score_weights
        $outlet = $this->makeOutlet('OUT-TRUST-004');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-C', 'q1', 'yes', now());

        $score = (new TrustScoreCalculator)->compute($submission);

        $this->assertSame(TrustScoreCalculator::DEFAULT_WEIGHTS, $score['weights']);
    }
}
