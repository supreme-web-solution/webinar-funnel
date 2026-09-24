<?php

namespace Tests\Feature\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCampaignPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_campaign_squeeze_and_bonus_pages_load(): void
    {
        $user = User::factory()->create(['username' => 'acme']);

        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'Affiliate OS',
            'slug' => 'affiliate-os',
            'type' => 'sales',
            'status' => 'published',
            'affiliate_link' => 'https://example.com/hop',
        ]);

        CampaignPage::query()->create([
            'campaign_id' => $campaign->id,
            'page_type' => 'squeeze',
            'content' => [
                'headline' => 'Get the guide',
                'cta' => 'Download',
            ],
        ]);

        CampaignPage::query()->create([
            'campaign_id' => $campaign->id,
            'page_type' => 'bonus',
            'content' => [
                'headline' => 'Your bonuses',
                'cta' => 'Get the offer',
            ],
        ]);

        $this->get(route('public.campaign.page', [
            'username' => 'acme',
            'slug' => 'affiliate-os',
            'page' => 'squeeze',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/campaign/Squeeze')
                ->where('content.headline', 'Get the guide'));

        $this->get(route('public.campaign.page', [
            'username' => 'acme',
            'slug' => 'affiliate-os',
            'page' => 'bonus',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/campaign/Bonus')
                ->where('content.headline', 'Your bonuses')
                ->where('content.affiliate_url', fn ($url) => is_string($url) && str_contains($url, '/r/')));
    }

    public function test_campaign_optin_creates_lead(): void
    {
        $user = User::factory()->create(['username' => 'seller']);

        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'Lead Test',
            'slug' => 'lead-test',
            'type' => 'sales',
            'status' => 'published',
        ]);

        CampaignPage::query()->create([
            'campaign_id' => $campaign->id,
            'page_type' => 'squeeze',
            'content' => ['headline' => 'Join'],
        ]);

        $response = $this->post(route('public.campaign.optin', [
            'username' => 'seller',
            'slug' => 'lead-test',
        ]), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('campaign_leads', [
            'campaign_id' => $campaign->id,
            'email' => 'jane@example.com',
        ]);
    }
}
