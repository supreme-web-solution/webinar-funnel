<?php

namespace Tests\Feature\Funnels;

use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Models\FunnelSetting;
use App\Models\SocialAccount;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FunnelPromotionFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeFunnel(User $user): Funnel
    {
        $template = Template::query()->create([
            'name' => 'Promotion Template',
            'slug' => 'promotion-template-'.uniqid(),
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'Promo Funnel',
            'slug' => 'promo-funnel-'.uniqid(),
            'status' => 'draft',
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
            'webinar_cta_label' => 'Join now',
            'webinar_cta_url' => 'https://example.com/offer',
        ]);

        return $funnel;
    }

    public function test_user_can_create_and_schedule_promotion_post(): void
    {
        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);

        $response = $this->actingAs($user)->post(route('funnels.promotion.posts.store', $funnel), [
            'topic' => 'How to improve webinar conversions',
            'content_type' => 'text',
            'platforms' => ['twitter', 'youtube'],
            'publish_mode' => 'approve_first',
            'auto_generate' => false,
        ]);

        $response->assertRedirect();
        $post = FunnelPromotionPost::query()->where('funnel_id', $funnel->id)->first();
        $this->assertNotNull($post);

        $scheduleAt = now()->addDay()->startOfHour();
        $scheduleResponse = $this->actingAs($user)->patch(
            route('funnels.promotion.posts.schedule', [$funnel, $post]),
            ['scheduled_for' => $scheduleAt->toIso8601String(), 'timezone' => 'UTC']
        );

        $scheduleResponse->assertRedirect();
        $this->assertDatabaseHas('funnel_promotion_posts', [
            'id' => $post->id,
            'status' => FunnelPromotionPost::STATUS_SCHEDULED,
        ]);
    }

    public function test_user_can_generate_topic_suggestions(): void
    {
        config([
            'services.openai.api_key' => '',
            'promotion.default_sequence_size' => 10,
        ]);

        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);

        $response = $this->actingAs($user)->post(route('funnels.promotion.topics.generate', $funnel), [
            'count' => 10,
            'context' => 'Focus on social growth and lead quality.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('funnel_promotion_topic_suggestions', 10);
    }

    public function test_publish_endpoint_marks_post_as_published_with_zernio_success(): void
    {
        config([
            'services.zernio.base_url' => 'https://zernio.test/api',
            'services.zernio.api_key' => 'test_key',
            'promotion.zernio.default_publish_endpoint' => '/v1/posts',
        ]);

        Http::fake([
            'https://zernio.test/api/v1/posts' => Http::response([
                'data' => ['id' => 'post_123'],
            ], 200),
        ]);

        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'platform' => 'twitter',
            'platform_username' => 'acct',
            'zernio_account_id' => 'acct_1',
            'daily_post_limit' => 50,
            'posts_today' => 0,
        ]);

        $post = FunnelPromotionPost::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'topic' => 'Launch announcement',
            'content_type' => FunnelPromotionPost::TYPE_TEXT,
            'platforms' => ['twitter'],
            'publish_mode' => FunnelPromotionPost::MODE_APPROVE_FIRST,
            'status' => FunnelPromotionPost::STATUS_READY,
            'text_body' => 'Launch day post body.',
            'cta_url' => 'https://example.com/offer',
            'cta_label' => 'Join now',
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($user)->post(route('funnels.promotion.posts.publish', [$funnel, $post]), [
            'sync' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('funnel_promotion_posts', [
            'id' => $post->id,
            'status' => FunnelPromotionPost::STATUS_PUBLISHED,
        ]);
    }
}
