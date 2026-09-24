<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialTrafficConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_redirect_shows_friendly_message_when_zernio_subscription_inactive(): void
    {
        config([
            'services.zernio.api_key' => 'test-key',
            'services.zernio.enabled' => true,
            'services.zernio.base_url' => 'https://zernio.com/api',
        ]);

        $user = User::factory()->create([
            'zernio_profile_id' => 'prof_test',
        ]);

        Http::fake([
            'zernio.com/api/v1/connect/facebook*' => Http::response([
                'error' => 'Payment required: your subscription is inactive.',
                'code' => 'PAYMENT_REQUIRED',
            ], 402),
        ]);

        $response = $this->actingAs($user)->get('/settings/social-traffic/facebook/redirect');

        $response->assertRedirect(route('settings.social-traffic.edit'));
        $response->assertSessionHasErrors(['facebook' => 'Social account connections are not available right now. Please try again later or contact support.']);
    }

    public function test_threads_and_pinterest_connect_redirect_routes_exist(): void
    {
        config([
            'services.zernio.api_key' => 'test-key',
            'services.zernio.enabled' => true,
            'services.zernio.base_url' => 'https://zernio.com/api',
        ]);

        $user = User::factory()->create([
            'zernio_profile_id' => 'prof_test',
        ]);

        Http::fake([
            'zernio.com/api/v1/connect/threads*' => Http::response(['url' => 'https://zernio.com/oauth/threads'], 200),
            'zernio.com/api/v1/connect/pinterest*' => Http::response(['url' => 'https://zernio.com/oauth/pinterest'], 200),
        ]);

        $this->actingAs($user)
            ->get('/settings/social-traffic/threads/redirect')
            ->assertRedirect('https://zernio.com/oauth/threads');

        $this->actingAs($user)
            ->get('/settings/social-traffic/pinterest/redirect')
            ->assertRedirect('https://zernio.com/oauth/pinterest');
    }
}
