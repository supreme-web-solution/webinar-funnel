<?php

namespace Tests\Unit\Services\Social;

use App\Services\Social\TwitterTextLimiter;
use Tests\TestCase;

class TwitterTextLimiterTest extends TestCase
{
    public function test_clamps_long_thread_part_under_280(): void
    {
        $limiter = new TwitterTextLimiter;
        $long = str_repeat('Insight about content marketing and growth. ', 12);

        $clamped = $limiter->clamp($long);

        $this->assertLessThanOrEqual(280, $limiter->effectiveLength($clamped));
        $this->assertNotSame('', $clamped);
    }

    public function test_urls_count_as_23_characters(): void
    {
        $limiter = new TwitterTextLimiter;
        $text = 'Check this out https://example.com/very/long/path/that/would/otherwise/be/huge';

        $this->assertLessThanOrEqual(
            280,
            $limiter->effectiveLength($limiter->clamp($text)),
        );
    }

    public function test_strips_markdown_before_clamp(): void
    {
        $limiter = new TwitterTextLimiter;
        $text = '**Bold headline** about [Sign up](https://example.com/offer) today';

        $clamped = $limiter->clamp($text);

        $this->assertStringNotContainsString('**', $clamped);
        $this->assertStringContainsString('Sign up', $clamped);
    }

    public function test_strips_localhost_urls_from_thread_parts(): void
    {
        $limiter = new TwitterTextLimiter;
        $text = 'Learn more: http://127.0.0.1:8000/william_victor/offer';

        $sanitized = $limiter->sanitizeForPublish($text);

        $this->assertStringNotContainsString('127.0.0.1', $sanitized);
        $this->assertSame('Learn more:', $sanitized);
    }

    public function test_keeps_public_urls_in_thread_parts(): void
    {
        $limiter = new TwitterTextLimiter;
        $text = 'Join now: https://example.com/offer';

        $sanitized = $limiter->sanitizeForPublish($text);

        $this->assertStringContainsString('https://example.com/offer', $sanitized);
    }
}
