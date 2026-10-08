<?php

namespace App\Services\Ingestion;

use App\Enums\PaymentWalletState;
use App\Enums\SubmissionStatus;
use App\Models\Answer;
use App\Models\Media;
use App\Models\Outlet;
use App\Models\PaymentStatus;
use App\Models\Price;
use App\Models\Quest;
use App\Models\QuestAcceptance;
use App\Models\Submission;
use App\Models\SubmissionTrustScore;
use App\Services\Pricing\MarketPriceRangeResolver;
use App\Services\Qa\QaRuleEngine;
use App\Services\Trust\TrustScoreCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a validated ingestion envelope into the mirrored, flexible
 * {question_id, value} rows the QA engine and dashboard read, then runs
 * Layer 1 automated QA. submission_id is the idempotency key: re-posting the
 * same submission_id returns the existing record untouched rather than
 * reprocessing it.
 */
class SubmissionIngestionService
{
    public function __construct(
        private QaRuleEngine $qaRuleEngine,
        private TrustScoreCalculator $trustScoreCalculator,
    ) {
    }

    public function ingest(array $envelope): Submission
    {
        $existing = Submission::find($envelope['submission_id']);
        if ($existing) {
            return $existing->loadMissing(['quest', 'outlet', 'answers', 'media', 'prices', 'qaFlags', 'trustScore']);
        }

        $quest = Quest::where('form_code', $envelope['quest_id'])->first();
        if (! $quest) {
            throw ValidationException::withMessages([
                'quest_id' => "No quest is registered with form_code [{$envelope['quest_id']}].",
            ]);
        }

        $outlet = Outlet::where('code', $envelope['outlet_id'])->first();
        if (! $outlet) {
            throw ValidationException::withMessages([
                'outlet_id' => "No outlet is registered with code [{$envelope['outlet_id']}]. Register the outlet/baseline reference data first.",
            ]);
        }

        // Every incoming timestamp is normalized to UTC explicitly here.
        // Eloquent's `datetime` cast does NOT convert an offset like
        // "+03:00" to the app timezone on save — it silently stores the
        // wall-clock digits as if they were already UTC, which is wrong by
        // exactly the source offset. Passing an already-UTC Carbon instance
        // sidesteps that entirely.
        $surveyStartAt = Carbon::parse($envelope['timestamps']['survey_start_at'])->utc();
        $surveyEndAt = Carbon::parse($envelope['timestamps']['survey_end_at'])->utc();
        $submittedAt = Carbon::parse($envelope['timestamps']['submitted_at'])->utc();

        // Time Validation: resolve the matching "quest accepted" event (if
        // the Mini App reported one) to seed the accept-to-start interval.
        // Most recent acceptance by this agent, for this quest+outlet, no
        // later than the survey start — never a later, unrelated acceptance.
        $acceptance = QuestAcceptance::where('quest_id', $quest->id)
            ->where('outlet_id', $outlet->id)
            ->where('agent_id', $envelope['agent_id'])
            ->where('accepted_at', '<=', $surveyStartAt)
            ->orderByDesc('accepted_at')
            ->first();

        $submission = DB::transaction(function () use ($envelope, $quest, $outlet, $acceptance, $surveyStartAt, $surveyEndAt, $submittedAt) {
            $submission = Submission::create([
                'id' => $envelope['submission_id'],
                'quest_id' => $quest->id,
                'config_version' => $envelope['config_version'],
                'agent_id' => $envelope['agent_id'],
                'outlet_id' => $outlet->id,
                'quest_accepted_at' => $acceptance?->accepted_at,
                'survey_start_at' => $surveyStartAt,
                'survey_end_at' => $surveyEndAt,
                'submitted_at' => $submittedAt,
                'gps_lat' => $envelope['gps']['lat'],
                'gps_lng' => $envelope['gps']['lng'],
                'gps_accuracy_m' => $envelope['gps']['accuracy_m'] ?? null,
                'client_app_version' => $envelope['client_app_version'] ?? null,
                'status' => SubmissionStatus::Received,
            ]);

            foreach ($envelope['answers'] ?? [] as $answer) {
                Answer::create([
                    'submission_id' => $submission->id,
                    'question_id' => $answer['question_id'],
                    'input_type' => $answer['input_type'],
                    'row_id' => $answer['row_id'] ?? null,
                    'value' => array_key_exists('value', $answer) ? $answer['value'] : null,
                    'media_ref' => $answer['media_ref'] ?? null,
                ]);
            }

            // NOTE: file_ref is stored exactly as received (URL or path). The
            // real Mini App multipart upload contract — bytes in, local
            // storage path recorded back — is Phase 2 (see project CLAUDE.md).
            foreach ($envelope['media'] ?? [] as $media) {
                Media::create([
                    'media_ref' => $media['media_ref'],
                    'submission_id' => $submission->id,
                    'question_id' => $media['question_id'],
                    'row_id' => $media['row_id'] ?? null,
                    'media_type' => $media['media_type'],
                    'file_path' => $media['file_ref'],
                    'captured_at' => Carbon::parse($media['captured_at'])->utc(),
                    'gps_at_capture_lat' => $media['gps_at_capture']['lat'] ?? null,
                    'gps_at_capture_lng' => $media['gps_at_capture']['lng'] ?? null,
                ]);
            }

            foreach ($envelope['prices'] ?? [] as $price) {
                $marketRange = MarketPriceRangeResolver::parse($price['market_range_price'] ?? null);

                Price::create([
                    'submission_id' => $submission->id,
                    'question_id' => $price['question_id'],
                    'sku_id' => $price['sku_id'],
                    'row_id' => $price['row_id'] ?? null,
                    'value' => $price['value'],
                    'currency' => $price['currency'],
                    'unit' => $price['unit'] ?? null,
                    'market_range_min' => $marketRange['min'] ?? null,
                    'market_range_max' => $marketRange['max'] ?? null,
                ]);
            }

            // amount reflects what's AT STAKE for this submission (the
            // quest's configured reward) regardless of outcome — wallet_state
            // is what actually happened to it (paid/withheld/still pending).
            // That split is what lets the dashboard show a real "amount
            // rejected" / "amount pending" total, not just "amount paid".
            PaymentStatus::create([
                'submission_id' => $submission->id,
                'agent_id' => $submission->agent_id,
                'wallet_state' => PaymentWalletState::Pending,
                'amount' => $quest->reward_amount,
                'currency' => $quest->reward_amount !== null ? $quest->reward_currency : null,
            ]);

            return $submission;
        });

        // Runs after the transaction commits: the rule engine downloads
        // photo bytes over HTTP to hash them, which shouldn't hold a DB
        // transaction open.
        $this->qaRuleEngine->run($submission);

        $score = $this->trustScoreCalculator->compute($submission);
        SubmissionTrustScore::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'gps_score' => $score['gps'],
                'photo_score' => $score['photo'],
                'time_score' => $score['time'],
                'completeness_score' => $score['completeness'],
                'audit_confirmation_score' => $score['audit_confirmation'],
                'total_score' => $score['total'],
                'weights_used' => $score['weights'],
            ]
        );

        $submission->status = SubmissionStatus::PendingReview;
        $submission->save();

        return $submission->fresh(['quest', 'outlet', 'answers', 'media', 'prices', 'qaFlags', 'trustScore']);
    }
}
