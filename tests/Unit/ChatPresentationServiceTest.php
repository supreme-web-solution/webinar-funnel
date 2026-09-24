<?php

namespace Tests\Unit;

use App\Services\AiEmployee\ChatPresentationService;
use Tests\TestCase;

class ChatPresentationServiceTest extends TestCase
{
    public function test_formats_quick_start_campaign_json_as_markdown(): void
    {
        $raw = json_encode([
            'created' => true,
            'queued' => true,
            'id' => 17,
            'name' => 'Doctorate Quality AI Content Generator',
            'edit_url' => '/campaigns/17/edit',
            'message' => 'Campaign created and AI generation is running in the background.',
        ], JSON_THROW_ON_ERROR);

        $text = app(ChatPresentationService::class)->formatLaunchResult('quick_start_campaign', $raw);

        $this->assertStringContainsString('**Campaign created:**', $text);
        $this->assertStringContainsString('#17', $text);
        $this->assertStringContainsString('[Open campaign editor](/campaigns/17/edit)', $text);
        $this->assertStringNotContainsString('"created"', $text);
    }
}
