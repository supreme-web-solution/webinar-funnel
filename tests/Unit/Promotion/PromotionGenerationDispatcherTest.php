<?php

namespace Tests\Unit\Promotion;

use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Models\FunnelSetting;
use App\Models\Template;
use App\Models\User;
use App\Services\Promotion\PromotionGenerationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PromotionGenerationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function makeFunnel(User $user): Funnel
    {
        $template = Template::query()->create([
            'name' => 'Test Template',
            'slug' => 'test-'.uniqid(),
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel-'.uniqid(),
            'status' => 'draft',
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
        ]);

        return $funnel;
    }

    public function test_image_post_includes_text_and_image_types(): void
    {
        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);
        $post = FunnelPromotionPost::factory()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'content_type' => FunnelPromotionPost::TYPE_IMAGE,
            'generation_context' => [
                'content_format' => 'pinterest_static_pin',
                'include_text' => true,
                'include_image' => true,
            ],
            'metadata' => [
                'format_key' => 'pinterest_static_pin',
            ],
        ]);

        $types = app(PromotionGenerationDispatcher::class)->generationTypesForPost($post);

        $this->assertContains(FunnelPromotionPost::TYPE_IMAGE, $types);
        $this->assertContains(FunnelPromotionPost::TYPE_TEXT, $types);
    }

    public function test_carousel_post_dispatches_text_only(): void
    {
        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);
        $post = FunnelPromotionPost::factory()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'content_type' => FunnelPromotionPost::TYPE_IMAGE,
            'generation_context' => [
                'content_format' => 'instagram_carousel',
                'include_text' => true,
            ],
            'metadata' => [
                'format_key' => 'instagram_carousel',
            ],
        ]);

        $types = app(PromotionGenerationDispatcher::class)->generationTypesForPost($post);

        $this->assertSame([FunnelPromotionPost::TYPE_TEXT], $types);
    }

    public function test_unsupported_video_provider_marks_post_failed_without_dispatching(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);
        $post = FunnelPromotionPost::factory()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'content_type' => FunnelPromotionPost::TYPE_VIDEO,
            'status' => FunnelPromotionPost::STATUS_GENERATING,
            'generation_context' => ['video_render_provider' => 'stock_broll'],
            'metadata' => ['video_render_provider' => 'stock_broll'],
        ]);

        app(PromotionGenerationDispatcher::class)->dispatch(
            $post,
            [FunnelPromotionPost::TYPE_VIDEO, FunnelPromotionPost::TYPE_TEXT],
            false,
        );

        $post->refresh();
        $this->assertSame(FunnelPromotionPost::STATUS_FAILED, $post->status);
        $this->assertStringContainsString('Only D-ID avatar', (string) $post->last_error);
        Queue::assertNothingPushed();
    }
}
