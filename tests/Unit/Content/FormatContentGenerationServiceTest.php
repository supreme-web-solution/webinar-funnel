<?php

namespace Tests\Unit\Content;

use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Models\FunnelSetting;
use App\Models\Template;
use App\Models\User;
use App\Services\Content\FormatContentGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormatContentGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.api_key' => '']);
    }

    private function makeFunnel(User $user): Funnel
    {
        $template = Template::query()->create([
            'name' => 'Fmt Template',
            'slug' => 'fmt-'.uniqid(),
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'Fmt Funnel',
            'slug' => 'fmt-funnel-'.uniqid(),
            'status' => 'draft',
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
        ]);

        return $funnel;
    }

    private function makePost(Funnel $funnel, User $user, string $formatKey, string $topic = 'Growth tips'): FunnelPromotionPost
    {
        return FunnelPromotionPost::factory()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'topic' => $topic,
            'content_type' => FunnelPromotionPost::TYPE_TEXT,
            'metadata' => ['format_key' => $formatKey],
            'generation_context' => ['content_format' => $formatKey],
            'cta_url' => 'https://example.com/offer',
            'cta_label' => 'Learn more',
        ]);
    }

    public function test_generates_x_thread_with_two_parts_and_no_link_reply(): void
    {
        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);
        $post = $this->makePost($funnel, $user, 'x_thread');

        $result = app(FormatContentGenerationService::class)->generate($funnel, $post);

        $this->assertSame('openai_format', $result['source']);
        $this->assertSame('thread', $result['format_payload']['generator']);
        $parts = $result['format_payload']['thread_parts'];
        $this->assertCount(2, $parts);
        $this->assertSame('', $result['format_payload']['link_reply']);
        $this->assertStringContainsString('---', $result['text_body']);
    }

    public function test_generates_poll_with_question_and_options(): void
    {
        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);
        $post = $this->makePost($funnel, $user, 'linkedin_poll', 'Email list growth');

        $result = app(FormatContentGenerationService::class)->generate($funnel, $post);

        $this->assertSame('poll', $result['format_payload']['generator']);
        $this->assertNotEmpty($result['format_payload']['question']);
        $this->assertGreaterThanOrEqual(2, count($result['format_payload']['options']));
        $this->assertStringContainsString('Email list growth', $result['text_body']);
    }

    public function test_generates_carousel_with_slides_in_payload(): void
    {
        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);
        $post = FunnelPromotionPost::factory()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'topic' => 'Carousel topic',
            'content_type' => FunnelPromotionPost::TYPE_IMAGE,
            'metadata' => ['format_key' => 'instagram_carousel'],
            'generation_context' => ['content_format' => 'instagram_carousel'],
        ]);

        $result = app(FormatContentGenerationService::class)->generate($funnel, $post);

        $this->assertSame('carousel', $result['format_payload']['generator']);
        $slides = $result['format_payload']['slides'];
        $this->assertNotEmpty($slides);
        $maxSlides = (int) config('promotion.carousel.max_slide_images', 6);
        $this->assertLessThanOrEqual($maxSlides, count($slides));
        $this->assertSame(count($slides), $result['format_payload']['slide_count']);
    }
}
