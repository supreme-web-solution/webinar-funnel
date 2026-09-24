<?php

namespace Tests\Unit;

use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Services\Ai\OpenRouterService;
use App\Services\Promotion\CarouselVisualDesignService;
use Tests\TestCase;

class CarouselVisualDesignServiceTest extends TestCase
{
    public function test_slide_prompt_enforces_shared_design_system(): void
    {
        $service = app(CarouselVisualDesignService::class);

        $design = [
            'background' => 'warm off-white #FAF7F2',
            'accent' => 'forest green #2D6A4F',
            'text' => 'charcoal #222222',
            'headline_font' => 'bold sans-serif',
            'body_font' => 'clean sans-serif',
            'layout' => 'headline top-left, body below',
            'style' => 'editorial minimal',
            'imagery' => 'one small icon only',
        ];

        $prompt = $service->buildSlideImagePrompt(
            funnel: new Funnel(['name' => 'Test Funnel']),
            post: new FunnelPromotionPost(['topic' => 'Keto for Moms']),
            slide: ['headline' => 'Snack Smart!', 'body' => 'Keep it simple.'],
            slideIndex: 1,
            totalSlides: 4,
            formatSpec: ['platform' => 'instagram', 'label' => 'Carousel', 'aspect_ratio' => '4:5', 'size' => '1080x1350'],
            designSystem: $design,
        );

        $this->assertStringContainsString('same designed template', $prompt);
        $this->assertStringContainsString('Instagram feed carousel', $prompt);
        $this->assertStringContainsString('NOT generic AI art', $prompt);
    }

    public function test_minimal_fallback_design_when_ai_unavailable(): void
    {
        $this->mock(OpenRouterService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $service = app(CarouselVisualDesignService::class);

        $design = $service->ensureDesignSystem(
            new Funnel(['name' => 'Funnel']),
            new FunnelPromotionPost([
                'topic' => 'Keto for Moms',
                'metadata' => ['format_payload' => ['slides' => [['headline' => 'Hook']]]],
            ]),
            ['platform' => 'instagram', 'aspect_ratio' => '4:5'],
            [['headline' => 'Hook']],
        );

        $this->assertSame('minimal_fallback', $design['source']);
        $this->assertSame('left_editorial', $design['layout_type']);
        $this->assertStringContainsString('#', $design['background']);
    }

    public function test_user_locked_layout_skips_ai_and_uses_template(): void
    {
        $this->mock(OpenRouterService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->never();
        });

        $service = app(CarouselVisualDesignService::class);

        $design = $service->ensureDesignSystem(
            new Funnel(['name' => 'Funnel']),
            new FunnelPromotionPost([
                'topic' => 'Keto for Moms',
                'generation_context' => [
                    'carousel_ai_template' => false,
                    'carousel_layout_type' => 'centered_hook',
                ],
                'metadata' => ['format_payload' => []],
            ]),
            ['platform' => 'instagram', 'aspect_ratio' => '4:5'],
            [['headline' => 'Hook']],
        );

        $this->assertSame('user_locked', $design['source']);
        $this->assertSame('centered_hook', $design['layout_type']);
    }
}
