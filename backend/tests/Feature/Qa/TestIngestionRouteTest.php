<?php

namespace Tests\Feature\Qa;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class TestIngestionRouteTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    public function test_accepts_a_manual_payload_without_the_ingestion_key(): void
    {
        $quest = $this->makeQuest('QST-MANUAL-001');
        $outlet = $this->makeOutlet('OUT-MANUAL-001');

        $envelope = [
            'submission_id' => (string) \Illuminate\Support\Str::uuid(),
            'quest_id' => $quest->form_code,
            'config_version' => $quest->config_version,
            'agent_id' => 'AGT-MANUAL-TEST',
            'outlet_id' => $outlet->code,
            'timestamps' => [
                'survey_start_at' => now()->subMinutes(5)->toIso8601String(),
                'survey_end_at' => now()->subMinutes(1)->toIso8601String(),
                'submitted_at' => now()->toIso8601String(),
            ],
            'gps' => ['lat' => 9.0, 'lng' => 38.7, 'accuracy_m' => 5],
            'answers' => [
                ['question_id' => 'q1', 'input_type' => 'single_choice', 'value' => 'yes'],
            ],
            'media' => [],
            'prices' => [],
        ];

        // Deliberately no X-Ingestion-Key header — the whole point is that
        // this dashboard-facing route doesn't require the Mini App's
        // shared secret.
        $response = $this->postJson('/api/qa/test-ingest', $envelope);

        $response->assertCreated();
        $response->assertJson(['success' => true, 'status' => 'pending']);

        $this->assertDatabaseHas('submissions', [
            'id' => $envelope['submission_id'],
            'agent_id' => 'AGT-MANUAL-TEST',
        ]);
    }

    public function test_rejects_a_payload_with_an_unknown_quest_id(): void
    {
        $envelope = [
            'submission_id' => (string) \Illuminate\Support\Str::uuid(),
            'quest_id' => 'QST-DOES-NOT-EXIST',
            'config_version' => 'v1',
            'agent_id' => 'AGT-MANUAL-TEST',
            'outlet_id' => 'OUT-DOES-NOT-EXIST',
            'timestamps' => [
                'survey_start_at' => now()->subMinutes(5)->toIso8601String(),
                'survey_end_at' => now()->subMinutes(1)->toIso8601String(),
                'submitted_at' => now()->toIso8601String(),
            ],
            'gps' => ['lat' => 9.0, 'lng' => 38.7, 'accuracy_m' => 5],
            'answers' => [],
            'media' => [],
            'prices' => [],
        ];

        $response = $this->postJson('/api/qa/test-ingest', $envelope);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('quest_id');
    }
}
