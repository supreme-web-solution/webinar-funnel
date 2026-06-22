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
        $user = User::factory()->create(['username' => 'promo-user']);
        $funnel = $this->makeFunnel($user);

        foreach (['twitter', 'youtube'] as $platform) {
            SocialAccount::query()->create([
                'user_id' => $user->id,
                'platform' => $platform,
                'platform_username' => '@test',
                'zernio_account_id' => "acct_{$platform}",
                'daily_post_limit' => 50,
                'posts_today' => 0,
            ]);
        }

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
        $this->assertSame(route('public.optin', [
            'username' => 'promo-user',
            'slug' => $funnel->slug,
        ]), $post->cta_url);

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

        \App\Models\TemplateVersion::query()->create([
            'template_id' => $funnel->template_id,
            'version' => 1,
            'is_current' => true,
            'optin_schema' => [
                'hero' => ['subheadline' => 'Discover how to grow webinar conversions with AI.'],
                'what_youll_discover' => [
                    'How to turn social posts into webinar registrations',
                    'Why most funnels leak leads before the offer',
                    'The 3-part content sequence that warms cold traffic',
                    'How to repurpose one webinar into a week of posts',
                    'What to say when prospects say they need to think about it',
                    'Proof angles that make your offer feel low-risk',
                    'How to write hooks that stop the scroll',
                    'The follow-up message that books more calls',
                    'Mistakes beginners make with promotion timing',
                    'How to test angles without burning your audience',
                ],
            ],
            'webinar_schema' => [],
            'default_settings' => [],
        ]);

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
                'message' => 'Post published successfully',
                'post' => [
                    '_id' => 'post_123',
                    'platforms' => [
                        [
                            'platform' => 'twitter',
                            'platformPostId' => 'tw_123',
                            'platformPostUrl' => 'https://twitter.com/example/status/tw_123',
                            'status' => 'published',
                        ],
                    ],
                ],
            ], 201),
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

    public function test_user_can_duplicate_ready_post_with_assets(): void
    {
        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);

        $post = FunnelPromotionPost::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'topic' => 'Original post',
            'content_type' => FunnelPromotionPost::TYPE_IMAGE,
            'platforms' => ['facebook'],
            'publish_mode' => FunnelPromotionPost::MODE_APPROVE_FIRST,
            'status' => FunnelPromotionPost::STATUS_PUBLISHED,
            'text_body' => 'Caption with image.',
            'timezone' => 'UTC',
        ]);

        $asset = \App\Models\FunnelPromotionAsset::query()->create([
            'promotion_post_id' => $post->id,
            'asset_type' => \App\Models\FunnelPromotionAsset::TYPE_IMAGE,
            'provider' => 'openai_image',
            'status' => \App\Models\FunnelPromotionAsset::STATUS_READY,
            'url' => 'https://example.com/image.png',
        ]);
        $post->update(['primary_asset_id' => $asset->id]);

        $response = $this->actingAs($user)->post(
            route('funnels.promotion.posts.duplicate', [$funnel, $post])
        );

        $response->assertRedirect();

        $copy = FunnelPromotionPost::query()
            ->where('funnel_id', $funnel->id)
            ->where('id', '!=', $post->id)
            ->first();

        $this->assertNotNull($copy);
        $this->assertSame(FunnelPromotionPost::STATUS_READY, $copy->status);
        $this->assertStringStartsWith('Caption with image.', $copy->text_body ?? '');
        $this->assertStringContainsString('—', $copy->text_body ?? '');
        $this->assertSame('Original post (copy)', $copy->topic);
        $this->assertNotNull($copy->primary_asset_id);
        $this->assertNotSame($post->primary_asset_id, $copy->primary_asset_id);
    }

    public function test_publish_waits_for_instagram_platform_post_id(): void
    {
        config([
            'services.zernio.base_url' => 'https://zernio.test/api',
            'services.zernio.api_key' => 'test_key',
            'promotion.zernio.default_publish_endpoint' => '/v1/posts',
            'promotion.zernio.publish_poll_attempts' => 2,
            'promotion.zernio.publish_poll_interval_seconds' => 0,
        ]);

        Http::fake([
            'https://zernio.test/api/v1/posts' => Http::response([
                'message' => 'Post accepted',
                'post' => [
                    '_id' => 'post_ig_async',
                    'platforms' => [
                        [
                            'platform' => 'instagram',
                            'status' => 'publishing',
                        ],
                    ],
                ],
            ], 201),
            'https://zernio.test/api/v1/posts/post_ig_async' => Http::sequence()
                ->push([
                    'post' => [
                        '_id' => 'post_ig_async',
                        'platforms' => [
                            [
                                'platform' => 'instagram',
                                'status' => 'publishing',
                            ],
                        ],
                    ],
                ])
                ->push([
                    'post' => [
                        '_id' => 'post_ig_async',
                        'status' => 'published',
                        'platforms' => [
                            [
                                'platform' => 'instagram',
                                'status' => 'published',
                                'platformPostId' => 'ig_123',
                                'platformPostUrl' => 'https://instagram.com/p/ig_123',
                            ],
                        ],
                    ],
                ]),
        ]);

        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'platform' => 'instagram',
            'platform_username' => 'acct',
            'zernio_account_id' => 'acct_ig',
            'daily_post_limit' => 50,
            'posts_today' => 0,
        ]);

        $post = FunnelPromotionPost::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'topic' => 'Instagram image post',
            'content_type' => FunnelPromotionPost::TYPE_IMAGE,
            'platforms' => ['instagram'],
            'publish_mode' => FunnelPromotionPost::MODE_APPROVE_FIRST,
            'status' => FunnelPromotionPost::STATUS_READY,
            'text_body' => 'Caption for Instagram.',
            'timezone' => 'UTC',
        ]);

        $asset = \App\Models\FunnelPromotionAsset::query()->create([
            'promotion_post_id' => $post->id,
            'asset_type' => \App\Models\FunnelPromotionAsset::TYPE_IMAGE,
            'provider' => 'openai_image',
            'status' => \App\Models\FunnelPromotionAsset::STATUS_READY,
            'url' => 'https://example.com/image.png',
        ]);
        $post->update(['primary_asset_id' => $asset->id]);

        $response = $this->actingAs($user)->post(route('funnels.promotion.posts.publish', [$funnel, $post]), [
            'sync' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('funnel_promotion_posts', [
            'id' => $post->id,
            'status' => FunnelPromotionPost::STATUS_PUBLISHED,
        ]);
    }

    public function test_publish_surfaces_instagram_error_message_from_zernio(): void
    {
        config([
            'services.zernio.base_url' => 'https://zernio.test/api',
            'services.zernio.api_key' => 'test_key',
            'promotion.zernio.default_publish_endpoint' => '/v1/posts',
            'promotion.zernio.publish_poll_attempts' => 1,
            'promotion.zernio.publish_poll_interval_seconds' => 0,
        ]);

        Http::fake([
            'https://zernio.test/api/v1/posts' => Http::response([
                'post' => [
                    '_id' => 'post_ig_failed',
                    'platforms' => [
                        [
                            'platform' => 'instagram',
                            'status' => 'failed',
                            'errorMessage' => 'Instagram access token has expired. Please reconnect your account.',
                        ],
                    ],
                ],
            ], 201),
            'https://zernio.test/api/v1/posts/post_ig_failed' => Http::response([
                'post' => [
                    '_id' => 'post_ig_failed',
                    'platforms' => [
                        [
                            'platform' => 'instagram',
                            'status' => 'failed',
                            'errorMessage' => 'Instagram access token has expired. Please reconnect your account.',
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'platform' => 'instagram',
            'platform_username' => 'acct',
            'zernio_account_id' => 'acct_ig',
            'daily_post_limit' => 50,
            'posts_today' => 0,
        ]);

        $post = FunnelPromotionPost::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'topic' => 'Instagram failure',
            'content_type' => FunnelPromotionPost::TYPE_IMAGE,
            'platforms' => ['instagram'],
            'publish_mode' => FunnelPromotionPost::MODE_APPROVE_FIRST,
            'status' => FunnelPromotionPost::STATUS_READY,
            'text_body' => 'Caption for Instagram.',
            'timezone' => 'UTC',
        ]);

        $asset = \App\Models\FunnelPromotionAsset::query()->create([
            'promotion_post_id' => $post->id,
            'asset_type' => \App\Models\FunnelPromotionAsset::TYPE_IMAGE,
            'provider' => 'openai_image',
            'status' => \App\Models\FunnelPromotionAsset::STATUS_READY,
            'url' => 'https://example.com/image.png',
        ]);
        $post->update(['primary_asset_id' => $asset->id]);

        $response = $this->actingAs($user)->post(route('funnels.promotion.posts.publish', [$funnel, $post]), [
            'sync' => true,
        ]);

        $response->assertRedirect();
        $post->refresh();
        $this->assertSame(FunnelPromotionPost::STATUS_FAILED, $post->status);
        $this->assertStringContainsString('access token has expired', (string) $post->last_error);
    }

    public function test_publish_truncates_tiktok_photo_title_and_moves_full_caption_to_description(): void
    {
        config([
            'services.zernio.base_url' => 'https://zernio.test/api',
            'services.zernio.api_key' => 'test_key',
            'promotion.zernio.default_publish_endpoint' => '/v1/posts',
        ]);

        Http::fake([
            'https://zernio.test/api/v1/posts' => function ($request) {
                $payload = $request->data();
                $this->assertLessThanOrEqual(90, mb_strlen((string) ($payload['content'] ?? '')));
                $this->assertSame(
                    'Understanding Character Consistency in AI Videos',
                    $payload['content']
                );
                $this->assertArrayHasKey('description', $payload['platforms'][0]['platformSpecificData'] ?? []);
                $this->assertStringContainsString(
                    'revolutionize your video content creation',
                    (string) $payload['platforms'][0]['platformSpecificData']['description']
                );

                return Http::response([
                    'post' => [
                        '_id' => 'post_tiktok',
                        'platforms' => [[
                            'platform' => 'tiktok',
                            'platformPostId' => 'tt_123',
                            'status' => 'published',
                        ]],
                    ],
                ], 201);
            },
        ]);

        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'platform' => 'tiktok',
            'platform_username' => 'acct',
            'zernio_account_id' => 'acct_tt',
            'daily_post_limit' => 50,
            'posts_today' => 0,
        ]);

        $longCaption = str_repeat('Are you ready to revolutionize your video content creation? ', 40);
        $post = FunnelPromotionPost::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'topic' => 'Understanding Character Consistency in AI Videos (copy)',
            'content_type' => FunnelPromotionPost::TYPE_IMAGE,
            'platforms' => ['tiktok'],
            'publish_mode' => FunnelPromotionPost::MODE_APPROVE_FIRST,
            'status' => FunnelPromotionPost::STATUS_READY,
            'text_body' => $longCaption,
            'timezone' => 'UTC',
        ]);

        $asset = \App\Models\FunnelPromotionAsset::query()->create([
            'promotion_post_id' => $post->id,
            'asset_type' => \App\Models\FunnelPromotionAsset::TYPE_IMAGE,
            'provider' => 'openai_image',
            'status' => \App\Models\FunnelPromotionAsset::STATUS_READY,
            'url' => 'https://example.com/image.png',
        ]);
        $post->update(['primary_asset_id' => $asset->id]);

        $response = $this->actingAs($user)->post(route('funnels.promotion.posts.publish', [$funnel, $post]), [
            'sync' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('funnel_promotion_posts', [
            'id' => $post->id,
            'status' => FunnelPromotionPost::STATUS_PUBLISHED,
        ]);
    }

    public function test_publish_sets_youtube_title_separately_from_description(): void
    {
        config([
            'services.zernio.base_url' => 'https://zernio.test/api',
            'services.zernio.api_key' => 'test_key',
            'promotion.zernio.default_publish_endpoint' => '/v1/posts',
        ]);

        Http::fake([
            'https://zernio.test/api/v1/posts' => function ($request) {
                $payload = $request->data();
                $title = (string) ($payload['platforms'][0]['platformSpecificData']['title'] ?? '');
                $this->assertLessThanOrEqual(100, mb_strlen($title));
                $this->assertSame('Short YouTube Title', $title);
                $this->assertGreaterThan(100, mb_strlen((string) ($payload['content'] ?? '')));

                return Http::response([
                    'post' => [
                        '_id' => 'post_yt',
                        'platforms' => [[
                            'platform' => 'youtube',
                            'platformPostId' => 'yt_123',
                            'platformPostUrl' => 'https://youtube.com/watch?v=yt_123',
                            'status' => 'published',
                        ]],
                    ],
                ], 201);
            },
        ]);

        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'platform' => 'youtube',
            'platform_username' => 'acct',
            'zernio_account_id' => 'acct_yt',
            'daily_post_limit' => 50,
            'posts_today' => 0,
        ]);

        $post = FunnelPromotionPost::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'topic' => 'Short YouTube Title',
            'content_type' => FunnelPromotionPost::TYPE_VIDEO,
            'platforms' => ['youtube'],
            'publish_mode' => FunnelPromotionPost::MODE_APPROVE_FIRST,
            'status' => FunnelPromotionPost::STATUS_READY,
            'text_body' => str_repeat('Long YouTube description with hashtags and CTA details. ', 30),
            'timezone' => 'UTC',
        ]);

        $asset = \App\Models\FunnelPromotionAsset::query()->create([
            'promotion_post_id' => $post->id,
            'asset_type' => \App\Models\FunnelPromotionAsset::TYPE_VIDEO,
            'provider' => 'did',
            'status' => \App\Models\FunnelPromotionAsset::STATUS_READY,
            'url' => 'https://example.com/video.mp4',
        ]);
        $post->update(['primary_asset_id' => $asset->id]);

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
