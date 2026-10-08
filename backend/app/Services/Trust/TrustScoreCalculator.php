<?php

namespace App\Services\Trust;

use App\Enums\QaReviewDecision;
use App\Models\Media;
use App\Models\QaFlag;
use App\Models\Submission;
use Illuminate\Support\Collection;

/**
 * Trust Score Weighted Composite (project CLAUDE.md): each QA dimension
 * scores 0-100 for a submission, then the dimension scores are combined
 * using the *quest's* weights — not one fixed system-wide formula —
 * because how much each dimension should count varies by quest type. An
 * admin sets those weights on the Quest at creation time
 * (Quest::trust_score_weights); quests that haven't been configured yet
 * fall back to DEFAULT_WEIGHTS.
 *
 * The Trust Score exists to separate premium/reliable agents from the rest
 * (payout privileges, priority queueing, lighter-touch review) — so every
 * dimension below is scored as a graduated deduction from a real
 * measured/flagged issue, not a blunt 3-bucket "fail/flag/pass" tier. A
 * submission with one minor photo issue should not score the same as one
 * with five severe ones.
 *
 * Finalized grade distribution: 30% photo quality/authenticity, 10% GPS
 * accuracy, 10% completeness of submitted forms, 30% timing/consistency
 * checks, 20% audit confirmation (Layer 2 human review outcome).
 */
class TrustScoreCalculator
{
    public const DEFAULT_WEIGHTS = [
        'photo' => 0.30,
        'gps' => 0.10,
        'completeness' => 0.10,
        'time' => 0.30,
        'audit_confirmation' => 0.20,
    ];

    // no_outlet_baseline_configured (BaselineLocationRule) fires when the
    // outlet has zero registered GPS/photo baselines at all — the distance
    // check below never ran for lack of anything to compare against, so
    // GPS shouldn't silently default to a full pass the same way it
    // wouldn't if the check had run and found a real problem.
    private const GPS_RULES = ['geofence_check', 'possible_wrong_location', 'poor_gps_accuracy', 'duplicate_gps_location', 'no_outlet_baseline_configured'];

    // Every automated photo-quality/authenticity check the QA engine can
    // raise (see QaRuleEngine's Photo*Rule set and BaselineVisualSimilarityRule/
    // DuplicatePhotoRule) — kept in sync with the rule_name lists in
    // frontend/src/pages/SubmissionReview.tsx's Photos section.
    // no_outlet_baseline_configured is listed here too — see GPS_RULES'
    // comment — but it's submission-scoped (no media_ref), so scorePhoto()
    // below handles it as a flat deduction on the averaged score rather
    // than through the normal per-photo path.
    private const PHOTO_RULES = [
        'baseline_photo_mismatch',
        'possible_reused_photo',
        'poor_photo_lighting',
        'low_photo_legibility',
        'small_product_in_frame',
        'low_photo_resolution',
        'photo_too_blurry',
        'low_photo_contrast',
        'excessive_photo_noise',
        'excessive_photo_glare',
        'no_outlet_baseline_configured',
    ];

    // no_required_fields_configured (RequiredFieldsRule) fires when the
    // quest itself has no required_question_ids set — "complete" was never
    // actually defined for this quest, so this isn't the same as a
    // submission that was checked and found complete.
    private const COMPLETENESS_RULES = ['missing_required_fields', 'no_required_fields_configured'];

    // Of the gps-dimension rule_names above, only these two are genuinely
    // "how far from the expected point" checks (both record their own
    // distance_m) — poor_gps_accuracy is about the device fix's own
    // reported precision, and duplicate_gps_location fires when a distance
    // is suspiciously SMALL (same spot reused), so neither belongs in the
    // "close enough, don't penalize" override below.
    private const GPS_DISTANCE_RULES = ['geofence_check', 'possible_wrong_location'];

    // A GPS reading is a single point, but a shop covers real physical
    // area — a photo/device fix within this radius of the expected
    // location (baseline spot or outlet) is fully trusted for Trust Score
    // purposes even when a stricter automated rule (e.g. a baseline's own
    // tighter registered radius) still raises a QaFlag for human review.
    // That QaFlag still exists and still surfaces in the dashboard; it
    // just doesn't drag the score down for a distance this small.
    private const GPS_FULL_TRUST_RADIUS_M = 40.0;

    private const FAIL_SCORE = 35.0;

    private const FLAG_SCORE = 75.0;

    private const PASS_SCORE = 100.0;

    // "Nothing existed to check this submission against" (no outlet
    // baseline, no required-fields definition) is a worse signal than "we
    // checked and found a minor issue" (FLAG_SCORE) — a flagged submission
    // was at least evaluated; an unconfigured one genuinely can't be
    // vouched for at all. Scored closer to FAIL_SCORE than FLAG_SCORE so a
    // quest/outlet sitting unconfigured actually shows up as needing
    // attention instead of blending into the "minor flag, still trustworthy"
    // tier.
    private const UNCONFIGURED_BASELINE_SCORE = 40.0;

    // "if it failed deduct by 50%" — an explicit half-score, kept as its
    // own named constant rather than reusing the generic FAIL_SCORE (35)
    // because this specific number came directly from the scoring spec.
    private const AUDIT_FAILED_SCORE = 50.0;

    // "if the quest flags for human confirmation deduct point" — covers
    // both "sent for backcheck confirmation" and "not reviewed yet" (Layer
    // 2 hasn't happened, so nothing has actually confirmed this
    // submission is clean — that's not the same as a full pass).
    private const AUDIT_PENDING_SCORE = 75.0;

    /**
     * Photo: per-flag point deduction, keyed by [result][severity]. Each
     * Photo*Rule already tiers its own severity from a real measured
     * threshold (e.g. PhotoBlurRule's hard_fail_variance vs
     * borderline_variance, PhotoResolutionRule's hard 1280x720 floor) —
     * reusing that severity here, rather than reinventing bespoke numeric
     * thresholds per rule, is what makes this "real world friendly": the
     * deduction already reflects how far outside an actual measured
     * threshold the photo fell. Deductions are summed per photo across
     * every issue found on it (not just the worst one), then each photo's
     * own 0-100 score is averaged across all photos in the submission —
     * proportional to how many real problems were found, not a flat tier.
     */
    private const PHOTO_DEDUCTIONS = [
        'fail' => ['high' => 40.0, 'medium' => 25.0, 'low' => 15.0],
        'flag' => ['high' => 20.0, 'medium' => 12.0, 'low' => 6.0],
    ];

    // Flat deduction applied to the whole averaged photo score (not
    // per-photo — see PHOTO_RULES' comment) when the outlet has no
    // baseline registered at all, so baseline comparison never ran for any
    // photo in the submission. Weighted higher than a single photo-quality
    // issue (PHOTO_DEDUCTIONS' own "fail/high" is 40) — this isn't "one bad
    // photo", it's "every photo in this submission went completely
    // unverified against anything".
    private const PHOTO_DEDUCTION_NO_OUTLET_BASELINE = 35.0;

    // Timing: one deduction per distinct check, sized by how strong a
    // fabrication/fraud signal it represents versus an honest operational
    // hiccup. "Too fast" and "stale photo" are graded harsher than "too
    // slow" because they're stronger authenticity red flags (a rushed or
    // reused submission), not just an agent running behind schedule.
    private const TIME_DEDUCTION_SEQUENCE_INCONSISTENT = 45.0; // chronologically impossible step ordering

    private const TIME_DEDUCTION_SURVEY_TOO_SLOW = 15.0; // excessive start-to-finish delay vs. the quest's own baseline — medium flag, deduct SOME points

    private const TIME_DEDUCTION_SURVEY_TOO_FAST_BASELINE = 35.0; // faster than the quest's admin-configured minimum — a real baseline was breached

    private const TIME_DEDUCTION_SURVEY_TOO_FAST_HEURISTIC = 20.0; // faster than plausible given the number of questions answered (no baseline needed)

    private const TIME_DEDUCTION_STALE_PHOTO = 40.0; // photo captured long before the survey — looks like a gallery/reused upload, not a live capture

    private const TIME_DEDUCTION_EXCESSIVE_ACCEPT_DELAY = 10.0; // accepted the quest but took a long time to actually start

    // A quest with no expected-duration baseline configured yet means the
    // survey-duration MAGNITUDE check (SurveyDurationRule) literally could
    // not run — that's a real verification gap, not a clean pass. Scoring
    // this the same as "checked and fine" would let every quest sit at a
    // rubber-stamped 100 for as long as nobody bothers setting up a
    // baseline in Baseline Management, which defeats the point of having
    // one. Weighted close to TIME_DEDUCTION_SURVEY_TOO_FAST_HEURISTIC (20)
    // — "we can't verify duration at all" is a bigger gap than "duration
    // was a bit off", not a rounding error.
    private const TIME_DEDUCTION_NO_DURATION_BASELINE = 30.0;

    // No QuestAcceptance record exists for this submission (see
    // TimeSequenceRule) — we can't confirm when, or whether, the agent
    // formally accepted the quest before starting, and the dedication
    // bonus below can never apply. That's a real accountability gap, not a
    // neutral "nothing to check", so it costs real points rather than
    // silently forfeiting only the upside (the bonus).
    private const TIME_DEDUCTION_NO_QUEST_ACCEPTANCE = 20.0;

    // Dedication bonus: accepting a quest and starting within this window
    // is a positive behavioral signal in its own right (the agent went
    // straight to the outlet), not just "avoided a penalty" — it actively
    // lifts the timing score, capped at the normal 0-100 range.
    private const DEDICATION_ACCEPT_TO_START_SECONDS = 300; // 5 minutes

    private const TIME_BONUS_DEDICATED_START = 12.0;

    /**
     * @return array{gps: float, photo: float, time: float, completeness: float, audit_confirmation: float, total: float, weights: array<string, float>}
     */
    public function compute(Submission $submission): array
    {
        $submission->loadMissing(['qaFlags', 'qaReviews', 'media']);
        $flags = $submission->qaFlags;

        $dimensionScores = [
            'gps' => $this->scoreGps($flags),
            'photo' => $this->scorePhoto($submission, $flags),
            'completeness' => $this->scoreDimension($flags, self::COMPLETENESS_RULES, 'no_required_fields_configured'),
            'time' => $this->scoreTime($submission, $flags),
            'audit_confirmation' => $this->scoreAuditConfirmation($submission),
        ];

        $weights = $this->resolveWeights($submission);
        $weightSum = array_sum($weights);

        $total = $weightSum > 0
            ? array_sum(array_map(
                fn (string $dimension) => $weights[$dimension] * $dimensionScores[$dimension],
                array_keys($dimensionScores)
            )) / $weightSum
            : array_sum($dimensionScores) / count($dimensionScores);

        return [
            ...$dimensionScores,
            'total' => round($total, 2),
            'weights' => $weights,
        ];
    }

    /**
     * $unconfiguredRuleName (when given) is checked ahead of the generic
     * flag tier — "nothing was configured to check this against" scores
     * worse than an ordinary flag (see UNCONFIGURED_BASELINE_SCORE), but
     * still better than a confirmed fail.
     *
     * @param  Collection<int, QaFlag>  $flags
     * @param  list<string>  $ruleNames
     */
    private function scoreDimension(Collection $flags, array $ruleNames, ?string $unconfiguredRuleName = null): float
    {
        $relevant = $flags->filter(fn (QaFlag $f) => in_array($f->rule_name, $ruleNames, true));

        if ($relevant->contains(fn (QaFlag $f) => $f->result->value === 'fail')) {
            return self::FAIL_SCORE;
        }

        if ($unconfiguredRuleName !== null && $relevant->contains(fn (QaFlag $f) => $f->rule_name === $unconfiguredRuleName)) {
            return self::UNCONFIGURED_BASELINE_SCORE;
        }

        if ($relevant->contains(fn (QaFlag $f) => $f->result->value === 'flag')) {
            return self::FLAG_SCORE;
        }

        return self::PASS_SCORE;
    }

    /**
     * @param  Collection<int, QaFlag>  $flags
     */
    private function scoreGps(Collection $flags): float
    {
        $gpsFlags = $flags->filter(fn (QaFlag $f) => in_array($f->rule_name, self::GPS_RULES, true));

        // Distance-based checks whose own recorded distance is still within
        // the "close enough" radius don't count against the score — only
        // genuinely-far distance flags, plus accuracy/duplicate flags
        // (never eligible for this override), still can.
        $scoredFlags = $gpsFlags->reject(function (QaFlag $f) {
            if (! in_array($f->rule_name, self::GPS_DISTANCE_RULES, true)) {
                return false;
            }

            $distance = $f->detail['distance_m'] ?? null;

            return $distance !== null && $distance <= self::GPS_FULL_TRUST_RADIUS_M;
        });

        if ($scoredFlags->contains(fn (QaFlag $f) => $f->result->value === 'fail')) {
            return self::FAIL_SCORE;
        }

        // "The outlet has no baseline at all" (BaselineLocationRule) is a
        // worse signal than an ordinary flag — nothing was checked, rather
        // than something being checked and found borderline — so it's
        // scored below FLAG_SCORE even though the flag's own `result` is
        // technically 'flag'. A confirmed fail elsewhere still wins (above).
        if ($scoredFlags->contains(fn (QaFlag $f) => $f->rule_name === 'no_outlet_baseline_configured')) {
            return self::UNCONFIGURED_BASELINE_SCORE;
        }

        if ($scoredFlags->contains(fn (QaFlag $f) => $f->result->value === 'flag')) {
            return self::FLAG_SCORE;
        }

        return self::PASS_SCORE;
    }

    /**
     * Graduated, per-photo scoring — see PHOTO_DEDUCTIONS. A submission
     * with no photos has nothing to penalize (PhotoResolutionRule etc. all
     * skip when there's no media), so it scores a full pass rather than
     * being punished for a quest that doesn't require photos.
     *
     * @param  Collection<int, QaFlag>  $flags
     */
    private function scorePhoto(Submission $submission, Collection $flags): float
    {
        $media = $submission->media;
        if ($media->isEmpty()) {
            return self::PASS_SCORE;
        }

        $photoFlags = $flags->filter(fn (QaFlag $f) => in_array($f->rule_name, self::PHOTO_RULES, true));

        // no_outlet_baseline_configured is submission-scoped (the whole
        // outlet has no baseline — it isn't any one photo's fault), so it's
        // pulled out of the per-photo pass and applied once to the
        // averaged score at the end instead.
        $noOutletBaseline = $photoFlags->contains(fn (QaFlag $f) => $f->rule_name === 'no_outlet_baseline_configured');
        $mediaScopedFlags = $photoFlags->filter(fn (QaFlag $f) => $f->rule_name !== 'no_outlet_baseline_configured');

        $score = self::PASS_SCORE;
        if ($mediaScopedFlags->isNotEmpty()) {
            $perPhotoScores = $media->map(function (Media $m) use ($mediaScopedFlags) {
                $ownFlags = $mediaScopedFlags->filter(
                    fn (QaFlag $f) => in_array($m->media_ref, $this->flagMediaRefs($f), true)
                );

                $photoScore = self::PASS_SCORE;
                foreach ($ownFlags as $f) {
                    $result = $f->result->value;
                    $severity = $f->severity ?? 'medium';
                    $photoScore -= self::PHOTO_DEDUCTIONS[$result][$severity]
                        ?? self::PHOTO_DEDUCTIONS[$result]['medium']
                        ?? 15.0;
                }

                return max(0.0, $photoScore);
            });

            $score = (float) $perPhotoScores->avg();
        }

        if ($noOutletBaseline) {
            $score = max(0.0, $score - self::PHOTO_DEDUCTION_NO_OUTLET_BASELINE);
        }

        return round($score, 2);
    }

    /**
     * A flag's affected photo(s) — normalizes both the singular
     * `media_ref` most Photo*Rule detail arrays use and the plural
     * `media_refs` DuplicatePhotoRule uses for its pair match. Mirrors
     * QaPresenter::flag()'s equivalent normalization for the dashboard.
     *
     * @return list<string>
     */
    private function flagMediaRefs(QaFlag $flag): array
    {
        $detail = $flag->detail ?? [];
        $refs = [];

        if (isset($detail['media_ref'])) {
            $refs[] = $detail['media_ref'];
        }

        if (isset($detail['media_refs']) && is_array($detail['media_refs'])) {
            $refs = array_merge($refs, $detail['media_refs']);
        }

        return $refs;
    }

    /**
     * Graduated timing score — see the TIME_DEDUCTION_* constants for the
     * reasoning behind each check's weight, and the dedication bonus for
     * why a fast accept→start turnaround actively helps rather than
     * merely avoiding a penalty.
     *
     * @param  Collection<int, QaFlag>  $flags
     */
    private function scoreTime(Submission $submission, Collection $flags): float
    {
        $score = self::PASS_SCORE;

        foreach ($flags as $f) {
            $detail = $f->detail ?? [];

            $deduction = match (true) {
                $f->rule_name === 'time_sequence_inconsistent' => self::TIME_DEDUCTION_SEQUENCE_INCONSISTENT,
                $f->rule_name === 'excessive_accept_to_start_delay' => self::TIME_DEDUCTION_EXCESSIVE_ACCEPT_DELAY,
                $f->rule_name === 'survey_duration_outside_expected_range' && ($detail['direction'] ?? null) === 'too_fast' => self::TIME_DEDUCTION_SURVEY_TOO_FAST_BASELINE,
                $f->rule_name === 'survey_duration_outside_expected_range' && ($detail['direction'] ?? null) === 'too_slow' => self::TIME_DEDUCTION_SURVEY_TOO_SLOW,
                $f->rule_name === 'timestamp_consistency' && ($detail['check'] ?? null) === 'survey_too_fast' => self::TIME_DEDUCTION_SURVEY_TOO_FAST_HEURISTIC,
                $f->rule_name === 'timestamp_consistency' && ($detail['check'] ?? null) === 'stale_photo' => self::TIME_DEDUCTION_STALE_PHOTO,
                $f->rule_name === 'no_duration_baseline_configured' => self::TIME_DEDUCTION_NO_DURATION_BASELINE,
                $f->rule_name === 'no_quest_acceptance_recorded' => self::TIME_DEDUCTION_NO_QUEST_ACCEPTANCE,
                default => 0.0,
            };

            $score -= $deduction;
        }

        if ($submission->quest_accepted_at && $submission->survey_start_at) {
            $acceptToStartSeconds = $submission->quest_accepted_at->diffInSeconds($submission->survey_start_at);

            if ($acceptToStartSeconds >= 0 && $acceptToStartSeconds <= self::DEDICATION_ACCEPT_TO_START_SECONDS) {
                $score += self::TIME_BONUS_DEDICATED_START;
            }
        }

        return max(0.0, min(self::PASS_SCORE, $score));
    }

    /**
     * "Audit confirmation" tracks Layer 2 (human review), not Layer 1
     * automated flags: the most recent QaReview decision on this
     * submission is what confirms — or fails to confirm — that everything
     * automated QA saw checks out. Per spec: no flag (approved) = 100%,
     * flagged for human confirmation (backcheck/send-back, or not yet
     * reviewed at all) = a partial deduction, failed (rejected) = 50%.
     */
    private function scoreAuditConfirmation(Submission $submission): float
    {
        $latestReview = $submission->qaReviews
            ->sortByDesc('reviewed_at')
            ->first();

        if (! $latestReview) {
            return self::AUDIT_PENDING_SCORE;
        }

        return match ($latestReview->decision) {
            QaReviewDecision::Approve => self::PASS_SCORE,
            QaReviewDecision::Reject => self::AUDIT_FAILED_SCORE,
            QaReviewDecision::FlagBackcheck, QaReviewDecision::SendBack => self::AUDIT_PENDING_SCORE,
        };
    }

    /**
     * @return array<string, float>
     */
    private function resolveWeights(Submission $submission): array
    {
        $configured = $submission->quest?->trust_score_weights;

        if (empty($configured)) {
            return self::DEFAULT_WEIGHTS;
        }

        // Fill in any dimension the admin didn't set with 0 rather than
        // crashing — an intentionally omitted dimension just contributes
        // nothing to the composite.
        return array_merge(
            array_fill_keys(array_keys(self::DEFAULT_WEIGHTS), 0.0),
            array_intersect_key($configured, self::DEFAULT_WEIGHTS)
        );
    }
}
