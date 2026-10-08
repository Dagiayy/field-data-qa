<?php

namespace Tests\Support;

use App\Enums\QaFlagResult;
use App\Enums\QuestType;
use App\Enums\SubmissionStatus;
use App\Models\Answer;
use App\Models\Outlet;
use App\Models\QaFlag;
use App\Models\Quest;
use App\Models\Submission;
use Carbon\CarbonInterface;

trait CreatesAnswerPatternFixtures
{
    private function makeQuest(string $formCode, ?array $trustScoreWeights = null): Quest
    {
        return Quest::create([
            'form_code' => $formCode,
            'title' => 'Test Quest '.$formCode,
            'quest_type' => QuestType::Quest,
            'config_version' => $formCode.'_v1',
            'geofence_center_lat' => 9.0,
            'geofence_center_lng' => 38.7,
            'geofence_radius_m' => 5000,
            'trust_score_weights' => $trustScoreWeights,
            'active' => true,
        ]);
    }

    private function makeFlag(Submission $submission, string $ruleName, QaFlagResult $result): QaFlag
    {
        return QaFlag::create([
            'submission_id' => $submission->id,
            'rule_name' => $ruleName,
            'result' => $result,
            'severity' => $result === QaFlagResult::Fail ? 'high' : 'medium',
            'detail' => [],
        ]);
    }

    private function makeOutlet(string $code): Outlet
    {
        return Outlet::create([
            'code' => $code,
            'name' => 'Test Outlet '.$code,
            'gps_lat' => 9.0,
            'gps_lng' => 38.7,
            'gps_radius_m' => 50,
            'active' => true,
        ]);
    }

    private function makeSubmission(
        Quest $quest,
        Outlet $outlet,
        string $agentId,
        string $questionId,
        mixed $answerValue,
        CarbonInterface $submittedAt,
        string $inputType = 'single_choice',
    ): Submission {
        $submission = Submission::create([
            'quest_id' => $quest->id,
            'config_version' => $quest->config_version,
            'agent_id' => $agentId,
            'outlet_id' => $outlet->id,
            'survey_start_at' => $submittedAt,
            'survey_end_at' => $submittedAt,
            'submitted_at' => $submittedAt,
            'gps_lat' => 9.0,
            'gps_lng' => 38.7,
            'status' => SubmissionStatus::PendingReview,
        ]);

        Answer::create([
            'submission_id' => $submission->id,
            'question_id' => $questionId,
            'input_type' => $inputType,
            'value' => $answerValue,
        ]);

        return $submission->fresh(['answers']);
    }
}
