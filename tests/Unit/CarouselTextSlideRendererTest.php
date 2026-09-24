<?php

namespace Tests\Unit;

use App\Services\Promotion\CarouselTextSlideRenderer;
use Tests\TestCase;

class CarouselTextSlideRendererTest extends TestCase
{
    public function test_parse_color_extracts_hex(): void
    {
        $renderer = new CarouselTextSlideRenderer;

        $this->assertSame([250, 247, 242], $renderer->parseColor('warm off-white #FAF7F2', [0, 0, 0]));
        $this->assertSame([15, 118, 110], $renderer->parseColor('#0F766E', [0, 0, 0]));
    }

    public function test_resolve_layout_types(): void
    {
        $renderer = new CarouselTextSlideRenderer;

        $this->assertSame('centered_hook', $renderer->resolveLayoutType(['layout_type' => 'centered_hook']));
        $this->assertSame('accent_header', $renderer->resolveLayoutType(['layout' => 'accent header band']));
        $this->assertSame('bottom_stack', $renderer->resolveLayoutType(['layout_type' => 'bottom_stack']));
        $this->assertSame('side_stripe', $renderer->resolveLayoutType(['layout_type' => 'side_stripe']));
        $this->assertSame('bold_statement', $renderer->resolveLayoutType(['layout_type' => 'bold_statement']));
        $this->assertSame('left_editorial', $renderer->resolveLayoutType(['layout_type' => 'left_editorial']));
        $this->assertSame('dark_spotlight', $renderer->resolveLayoutType(['layout_type' => 'dark_spotlight']));
        $this->assertSame('dark_editorial', $renderer->resolveLayoutType(['layout' => 'dark editorial left']));
        $this->assertCount(9, CarouselTextSlideRenderer::availableLayoutTypes());
        $this->assertCount(9, CarouselTextSlideRenderer::layoutCatalog());
        $locked = CarouselTextSlideRenderer::lockedDesignForLayout('dark_spotlight');
        $this->assertSame('dark_spotlight', $locked['layout_type']);
        $this->assertSame('user_locked', $locked['source']);
        $this->assertSame('#121214', $locked['background']);
    }

    public function test_sanitize_text_strips_emojis(): void
    {
        $renderer = new CarouselTextSlideRenderer;
        $clean = $renderer->sanitizeText('Avoid These Mistakes! 🚨');

        $this->assertStringNotContainsString('🚨', $clean);
        $this->assertStringContainsString('Avoid These Mistakes!', $clean);
    }
}
