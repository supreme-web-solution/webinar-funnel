<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignPage;
use App\Models\Funnel;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrafficHubOptinRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_traffic_hub_url_redirects_to_published_campaign_squeeze(): void
    {
        $user = User::factory()->create(['username' => 'rnet7']);
        $template = Template::query()->create([
            'name' => 'Blank',
            'slug' => 'blank-traffic-redirect',
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'AI Content App',
            'slug' => 'new-web-app-create-doctorate-quality-fully-undetectable-ai-content-for-any',
            'type' => 'sales',
            'status' => 'published',
        ]);

        CampaignPage::query()->create([
            'campaign_id' => $campaign->id,
            'page_type' => 'squeeze',
            'content' => ['headline' => 'Get the guide'],
        ]);

        Funnel::query()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'template_id' => $template->id,
            'name' => 'Traffic Hub',
            'slug' => 'new-web-app-create-doctorate-quality-fully-undetectable-ai-content-for-any-traffic',
            'status' => 'draft',
            'meta' => ['campaign_variant' => 'traffic'],
        ]);

        $this->assertDatabaseHas('funnels', [
            'slug' => 'new-web-app-create-doctorate-quality-fully-undetectable-ai-content-for-any-traffic',
        ]);

        $found = Funnel::query()
            ->where('slug', 'new-web-app-create-doctorate-quality-fully-undetectable-ai-content-for-any-traffic')
            ->where('meta->campaign_variant', 'traffic')
            ->first();
        $this->assertNotNull($found, 'Traffic hub funnel should be findable by meta variant');

        $this->get('/rnet7/new-web-app-create-doctorate-quality-fully-undetectable-ai-content-for-any-traffic')
            ->assertRedirect(route('public.campaign.page', [
                'username' => 'rnet7',
                'slug' => 'new-web-app-create-doctorate-quality-fully-undetectable-ai-content-for-any',
                'page' => 'squeeze',
            ]));
    }

    public function test_traffic_hub_for_unpublished_campaign_still_404s(): void
    {
        $user = User::factory()->create(['username' => 'drafty']);
        $template = Template::query()->create([
            'name' => 'Blank 2',
            'slug' => 'blank-traffic-redirect-2',
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'Draft Campaign',
            'slug' => 'draft-campaign',
            'type' => 'sales',
            'status' => 'draft',
        ]);

        Funnel::query()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'template_id' => $template->id,
            'name' => 'Traffic Hub',
            'slug' => 'draft-campaign-traffic',
            'status' => 'draft',
            'meta' => ['campaign_variant' => 'traffic'],
        ]);

        $this->get('/drafty/draft-campaign-traffic')->assertNotFound();
    }
}
