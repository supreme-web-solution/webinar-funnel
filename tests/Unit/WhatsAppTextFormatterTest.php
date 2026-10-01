<?php

namespace Tests\Unit;

use App\Services\AiEmployee\WhatsAppTextFormatter;
use Tests\TestCase;

class WhatsAppTextFormatterTest extends TestCase
{
    public function test_markdown_app_links_become_full_urls(): void
    {
        config(['app.url' => 'https://autoaffiliate360.com']);

        $text = app(WhatsAppTextFormatter::class)->format(implode("\n", [
            '**Campaign created:** Doctorate-Quality AI Content Sales Campaign (#6)',
            'AI is building pages, bonuses, and emails in the background.',
            '[Open campaign editor](/campaigns/6/edit)',
        ]));

        $this->assertStringContainsString(
            'Open campaign editor: https://autoaffiliate360.com/campaigns/6/edit',
            $text,
        );
        $this->assertStringNotContainsString('[Open campaign editor]', $text);
        $this->assertStringNotContainsString('](/campaigns/6/edit)', $text);
    }

    public function test_absolute_markdown_links_are_left_as_plain_urls(): void
    {
        config(['app.url' => 'https://autoaffiliate360.com']);

        $text = app(WhatsAppTextFormatter::class)->format(
            '[Open campaign editor](https://autoaffiliate360.com/campaigns/6/edit)',
        );

        $this->assertSame(
            'Open campaign editor: https://autoaffiliate360.com/campaigns/6/edit',
            $text,
        );
    }
}
