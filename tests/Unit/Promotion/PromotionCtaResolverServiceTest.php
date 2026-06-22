<?php

namespace Tests\Unit\Promotion;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Template;
use App\Models\User;
use App\Services\Promotion\PromotionCtaResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionCtaResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_funnel_optin_url_instead_of_affiliate_request_link(): void
    {
        $user = User::factory()->create([
            'username' => 'admin',
        ]);

        $template = Template::query()->create([
            'name' => 'Test Template',
            'slug' => 'test-template',
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);

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
}
