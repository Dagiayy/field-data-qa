<?php

namespace Tests\Feature\Qa;

use App\Services\Qa\Rules\PhotoExposureRule;
use App\Services\Qa\Rules\PhotoLegibilityRule;
use App\Services\Qa\Rules\PhotoSizeFramingRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageTestControllerTest extends TestCase
{
    public function test_reports_ocr_confidence_on_the_real_0_to_100_scale_not_multiplied_again(): void
    {
        Storage::fake('public');
        Http::fake([
            '*/analyze' => Http::response([
                'image_width' => 800,
                'image_height' => 600,
                'text' => 'Tena Oil 1L',
                'avg_confidence' => 87.5,
                'words' => [
                    ['text' => 'Tena', 'confidence' => 90.0, 'x' => 10, 'y' => 10, 'width' => 40, 'height' => 20],
                ],
                'brightness_mean' => 130.0,
                'is_dark' => false,
                'is_overexposed' => false,
            ], 200),
        ]);

        $response = $this->postJson('/api/qa/image-test', [
            'image' => UploadedFile::fake()->image('shelf.jpg'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        // Was previously *100'd on top of an already-0-100-scale value —
        // would have shown 8750 instead of 87.5.
        $response->assertJsonPath('result.ocr_avg_confidence', 87.5);
        $response->assertJsonPath('result.image_width', 800);
        $response->assertJsonPath('result.word_count', 1);
        $response->assertJsonPath('result.words.0.text', 'Tena');
    }

    public function test_legibility_check_uses_the_real_rule_threshold(): void
    {
        Storage::fake('public');
        Http::fake([
            '*/analyze' => Http::response([
                'image_width' => 800,
                'image_height' => 600,
                'text' => '',
                'avg_confidence' => 0,
                'words' => [],
                'brightness_mean' => 130.0,
                'is_dark' => false,
                'is_overexposed' => false,
            ], 200),
        ]);

        $response = $this->postJson('/api/qa/image-test', [
            'image' => UploadedFile::fake()->image('blank.jpg'),
        ]);

        $response->assertOk();
        // No text detected at all (0% confidence) is below the 60% reject
        // threshold — a hard fail, not a soft flag.
        $response->assertJsonPath('result.checks.legibility.result', 'fail');
        $this->assertEquals(
            PhotoLegibilityRule::MIN_ACCEPTABLE_CONFIDENCE,
            $response->json('result.checks.legibility.min_acceptable_confidence')
        );
    }

    public function test_framing_and_exposure_checks_reflect_real_thresholds(): void
    {
        Storage::fake('public');
        Http::fake([
            '*/analyze' => Http::response([
                'image_width' => 1000,
                'image_height' => 1000,
                'text' => 'x',
                'avg_confidence' => 90.0,
                'words' => [
                    ['text' => 'x', 'confidence' => 90.0, 'x' => 0, 'y' => 0, 'width' => 5, 'height' => 5],
                ],
                'brightness_mean' => 250.0,
                'is_dark' => false,
                'is_overexposed' => true,
            ], 200),
        ]);

        $response = $this->postJson('/api/qa/image-test', [
            'image' => UploadedFile::fake()->image('tiny-text.jpg'),
        ]);

        $response->assertOk();
        // 5/1000 = 0.005, below HARD_FAIL_RATIO (0.015) -> fail
        $response->assertJsonPath('result.checks.framing.result', 'fail');
        $this->assertEquals(PhotoSizeFramingRule::HARD_FAIL_RATIO, $response->json('result.checks.framing.hard_fail_ratio'));
        $response->assertJsonPath('result.checks.exposure.result', 'flag');
        $this->assertEquals(PhotoExposureRule::OVEREXPOSED_THRESHOLD, $response->json('result.checks.exposure.overexposed_threshold'));
    }

    public function test_returns_failure_gracefully_when_image_service_is_unreachable(): void
    {
        Storage::fake('public');
        Http::fake([
            '*/analyze' => Http::response([], 500),
        ]);

        $response = $this->postJson('/api/qa/image-test', [
            'image' => UploadedFile::fake()->image('shelf.jpg'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('result', null);
    }
}
