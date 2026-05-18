<?php

namespace Tests\Unit;

use App\Models\FunnelSetting;
use App\Models\Mention;
use App\Services\TrafficAi\TrafficReplyGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrafficReplyGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_uses_owner_context_and_contest_hook(): void
    {
        config(['services.openai.api_key' => null]);

        $settings = new FunnelSetting([
            'traffic_ai_extra_context' => 'Invite affiliates on JVZoo — 50% on each sale across all funnels. $500 contest too.',
        ]);

        $mention = new Mention([
            'title' => 'William Victor @Vickenconcept',
            'content' => 'Building Resume Tailor — a Chrome extension that tailors your resume. https://chromewebstore.google.com/example',
            'source_type' => 'Twitter',
        ]);

        $result = app(TrafficReplyGenerator::class)->generateWithMeta(
            $mention,
            $settings,
            'https://www.jvzoo.com/affiliate/example',
            'twitter',
        );

        $this->assertSame('fallback', $result['source']);
        $this->assertStringContainsString('https://www.jvzoo.com/affiliate/example', $result['text']);
        $this->assertStringNotContainsString('Saw your post about William Victor', $result['text']);
        $this->assertStringNotContainsString('wrap my head around it', strtolower($result['text']));
        $this->assertTrue(
            str_contains(strtolower($result['text']), 'contest')
                || str_contains($result['text'], '$500')
                || str_contains($result['text'], '50%'),
        );
    }

    public function test_openai_prompt_prioritizes_owner_context(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '$500 JVZoo contest — 50% commissions. Great fit for Resume Tailor promoters: https://www.jvzoo.com/affiliate/example']],
                ],
            ], 200),
        ]);

        config(['services.openai.api_key' => 'test-key']);

        $settings = new FunnelSetting([
            'traffic_ai_extra_context' => 'Invite affiliates on JVZoo — 50% on each sale. $500 contest.',
        ]);

        $mention = new Mention([
            'content' => 'Building Resume Tailor — Chrome extension for tailored resumes.',
            'source_type' => 'Twitter',
        ]);

        $result = app(TrafficReplyGenerator::class)->generateWithMeta(
            $mention,
            $settings,
            'https://www.jvzoo.com/affiliate/example',
            'twitter',
        );

        $this->assertSame('openai', $result['source']);
        $this->assertStringContainsString('$500', $result['text']);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $system = $body['messages'][0]['content'] ?? '';

            return str_contains($system, 'contest')
                && str_contains($system, 'mandatory');
        });
    }

    public function test_rejects_low_quality_openai_output_and_uses_context_fallback(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Saw your post about William — this helped me wrap my head around it: https://example.com']],
                ],
            ], 200),
        ]);

        config(['services.openai.api_key' => 'test-key']);

        $settings = new FunnelSetting([
            'traffic_ai_extra_context' => '50% JVZoo commissions and a $500 contest.',
        ]);

        $mention = new Mention([
            'content' => 'Building Resume Tailor — Chrome extension.',
            'source_type' => 'Twitter',
        ]);

        $result = app(TrafficReplyGenerator::class)->generateWithMeta(
            $mention,
            $settings,
            'https://www.jvzoo.com/affiliate/example',
            'twitter',
        );

        $this->assertSame('fallback', $result['source']);
        $this->assertStringNotContainsString('wrap my head around it', strtolower($result['text']));
    }
}
