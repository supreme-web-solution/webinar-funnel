<?php

namespace Tests\Unit\Promotion;

use App\Models\Campaign;
use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Models\FunnelSetting;
use App\Models\Template;
use App\Models\User;
use App\Services\Promotion\PromotionCtaResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionCtaResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_traffic_hub_resolves_to_campaign_squeeze_not_traffic_slug(): void
    {
        [$user, $template] = $this->userAndTemplate('promoter');

        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'AI Content App',
            'slug' => 'ai-content-app',
            'type' => 'sales',
            'status' => 'published',
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'template_id' => $template->id,
            'name' => 'AI Content App — Traffic Hub',
            'slug' => 'ai-content-app-traffic',
            'status' => 'draft',
            'meta' => ['campaign_variant' => 'traffic'],
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'webinar_cta_label' => 'Get free access',
        ]);

        $cta = app(PromotionCtaResolverService::class)->resolve($funnel);

        $this->assertSame(route('public.campaign.page', [
            'username' => 'promoter',
            'slug' => 'ai-content-app',
            'page' => 'squeeze',
        ]), $cta['url']);
        $this->assertStringNotContainsString('-traffic', (string) $cta['url']);
        $this->assertSame('Get free access', $cta['label']);
    }

    public function test_repairs_post_cta_that_points_at_traffic_hub(): void
    {
        [$user, $template] = $this->userAndTemplate('promoter2');

        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'KDP Campaign',
            'slug' => 'kdp-campaign',
            'type' => 'sales',
            'status' => 'published',
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'template_id' => $template->id,
            'name' => 'Traffic',
            'slug' => 'kdp-campaign-traffic',
            'status' => 'draft',
            'meta' => ['campaign_variant' => 'traffic'],
        ]);

        FunnelSetting::query()->create(['funnel_id' => $funnel->id]);

        $badUrl = route('public.optin', ['username' => 'promoter2', 'slug' => 'kdp-campaign-traffic']);
        $post = FunnelPromotionPost::query()->create([
            'funnel_id' => $funnel->id,
            'user_id' => $user->id,
            'content_type' => FunnelPromotionPost::TYPE_TEXT,
            'status' => FunnelPromotionPost::STATUS_READY,
            'platforms' => ['linkedin'],
            'cta_url' => $badUrl,
            'cta_label' => 'Sign up',
            'text_body' => 'Hello',
        ]);

        $repaired = app(PromotionCtaResolverService::class)->ensurePostCta($post);

        $this->assertSame(route('public.campaign.page', [
            'username' => 'promoter2',
            'slug' => 'kdp-campaign',
            'page' => 'squeeze',
        ]), $repaired->cta_url);
    }

    public function test_resolves_funnel_optin_url_instead_of_affiliate_request_link(): void
    {
        [$user, $template] = $this->userAndTemplate('admin');

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'Local Mator',
            'slug' => 'localmator',
            'status' => 'published',
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
            'webinar_cta_label' => 'Sign up right away',
            'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/435525',
        ]);

        $cta = app(PromotionCtaResolverService::class)->resolve($funnel);

        $this->assertSame(route('public.optin', [
            'username' => 'admin',
            'slug' => 'localmator',
        ]), $cta['url']);
        $this->assertSame('Sign up right away', $cta['label']);
    }

    public function test_falls_back_to_affiliate_link_when_optin_url_unavailable(): void
    {
        $user = User::factory()->create([
            'username' => null,
        ]);

        $template = Template::query()->create([
            'name' => 'Test Template',
            'slug' => 'test-template-2',
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'No Username Funnel',
            'slug' => 'no-username',
            'status' => 'draft',
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
            'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/435525',
        ]);

        $cta = app(PromotionCtaResolverService::class)->resolve($funnel);

        $this->assertSame('https://www.jvzoo.com/affiliate/affiliateinfonew/index/435525', $cta['url']);
    }

    /**
     * @return array{0: User, 1: Template}
     */
    private function userAndTemplate(string $username): array
    {
        $user = User::factory()->create(['username' => $username]);

        $template = Template::query()->create([
            'name' => 'Test Template '.$username,
            'slug' => 'test-template-'.$username,
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return [$user, $template];
    }
}
