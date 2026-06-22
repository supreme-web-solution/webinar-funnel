<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaidAdsFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_ad_accounts_route_hidden_when_paid_ads_disabled(): void
    {
        config(['promotion.ads.enabled' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings/ad-accounts')->assertNotFound();
    }

    public function test_funnel_ads_routes_hidden_when_paid_ads_disabled(): void
    {
        config(['promotion.ads.enabled' => false]);

        $user = User::factory()->create();
        $funnel = $this->makeFunnel($user);

        $this->actingAs($user)->get("/funnels/{$funnel->id}/ads")->assertNotFound();
    }

    private function makeFunnel(User $user): Funnel
    {
        $template = Template::query()->create([
            'name' => 'Test Template',
            'slug' => 'test-template-'.uniqid(),
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'Test Funnel',
            'slug' => 'test-funnel-'.uniqid(),
            'status' => 'draft',
        ]);
    }
}
