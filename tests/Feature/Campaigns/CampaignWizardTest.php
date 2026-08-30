<?php

namespace Tests\Feature\Campaigns;

use App\Models\Campaign;
use App\Models\TrackedLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_campaign(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('campaigns.store'), [
            'name' => 'Podshorts Test',
            'type' => 'sales',
            'offer_url' => 'https://example.com/sales',
            'affiliate_link' => 'https://example.com/aff',
            'offer_data' => ['product_name' => 'Podshorts'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('campaigns', [
            'user_id' => $user->id,
            'name' => 'Podshorts Test',
            'type' => 'sales',
        ]);
    }

    public function test_tracked_link_redirect_records_click(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'Test',
            'slug' => 'test',
            'type' => 'sales',
            'status' => 'draft',
        ]);

        $link = TrackedLink::query()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'code' => 'abc12345',
            'label' => 'Hop',
            'destination_url' => 'https://example.com/offer',
            'is_active' => true,
        ]);

        $response = $this->get(route('tracked-links.redirect', ['code' => $link->code]));
        $response->assertRedirect('https://example.com/offer');

        $this->assertDatabaseHas('tracked_link_clicks', [
            'tracked_link_id' => $link->id,
        ]);
        $this->assertSame(1, $link->fresh()->click_count);
    }
}
