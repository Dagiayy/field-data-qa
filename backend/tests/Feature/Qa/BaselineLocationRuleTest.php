<?php

namespace Tests\Feature\Qa;

use App\Models\Media;
use App\Services\Qa\Rules\BaselineLocationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnswerPatternFixtures;
use Tests\TestCase;

class BaselineLocationRuleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAnswerPatternFixtures;

    public function test_flags_no_outlet_baseline_configured_when_outlet_has_zero_baselines_and_media_present(): void
    {
        $quest = $this->makeQuest('QST-BASELOC-001');
        $outlet = $this->makeOutlet('OUT-BASELOC-001'); // no baselines registered
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-P', 'q1', 'yes', now());

        Media::create([
            'submission_id' => $submission->id,
            'media_ref' => 'media_001',
            'question_id' => 'q_photo',
            'media_type' => 'image',
            'file_path' => 'https://picsum.photos/seed/baseloc-001/4032/3024',
            'captured_at' => now(),
            'gps_at_capture_lat' => 9.0,
            'gps_at_capture_lng' => 38.7,
        ]);

        $flags = (new BaselineLocationRule(app(\App\Services\ImageSimilarity\ImageSimilarityService::class), app(\App\Services\ImageSimilarity\ImageEmbeddingService::class)))
            ->evaluate($submission->fresh(['media', 'outlet.baselines']));

        $this->assertCount(1, $flags);
        $this->assertSame('no_outlet_baseline_configured', $flags[0]['rule_name']);
        $this->assertSame('flag', $flags[0]['result']->value);
    }

    public function test_does_not_flag_when_outlet_has_zero_baselines_but_submission_has_no_media(): void
    {
        $quest = $this->makeQuest('QST-BASELOC-002');
        $outlet = $this->makeOutlet('OUT-BASELOC-002');
        $submission = $this->makeSubmission($quest, $outlet, 'AGT-Q', 'q1', 'yes', now());

        $flags = (new BaselineLocationRule(app(\App\Services\ImageSimilarity\ImageSimilarityService::class), app(\App\Services\ImageSimilarity\ImageEmbeddingService::class)))
            ->evaluate($submission->fresh(['media', 'outlet.baselines']));

        $this->assertCount(0, $flags);
    }
}
