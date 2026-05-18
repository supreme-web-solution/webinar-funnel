<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Zernio\ZernioSocialAccountSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZernioSocialAccountSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_persists_accounts_from_zernio_profile(): void
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
            'zernio.com/api/v1/accounts*' => Http::response([
                'accounts' => [
                    [
                        '_id' => 'acc_twitter_1',
                        'platform' => 'twitter',
                        'username' => 'myhandle',
                    ],
                    [
                        '_id' => 'acc_reddit_1',
                        'platform' => 'reddit',
                        'username' => 'my_reddit',
                    ],
                ],
            ], 200),
        ]);

        $synced = app(ZernioSocialAccountSync::class)->syncForUser($user);

        $this->assertEquals(['twitter', 'reddit'], $synced);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'platform' => 'twitter',
            'zernio_account_id' => 'acc_twitter_1',
            'platform_username' => '@myhandle',
        ]);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'platform' => 'reddit',
            'zernio_account_id' => 'acc_reddit_1',
        ]);
    }

    public function test_oauth_callback_exchanges_code_and_saves_account(): void
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
            'zernio.com/api/v1/connect/twitter' => Http::response([
                'account' => [
                    'accountId' => 'acc_from_oauth',
                    'platform' => 'twitter',
                    'username' => 'oauth_user',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get(
            '/settings/social-traffic/x/callback?code=abc&state=xyz'
        );

        $response->assertRedirect(route('settings.social-traffic.edit'));

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'platform' => 'twitter',
            'zernio_account_id' => 'acc_from_oauth',
            'platform_username' => '@oauth_user',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://zernio.com/api/v1/connect/twitter'
                && $request['code'] === 'abc'
                && $request['state'] === 'xyz'
                && $request['profileId'] === 'prof_test';
        });
    }

    public function test_social_traffic_page_syncs_accounts_on_load(): void
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
            'zernio.com/api/v1/accounts*' => Http::response([
                'accounts' => [
                    ['_id' => 'acc_yt', 'platform' => 'youtube', 'username' => 'My Channel'],
                ],
            ], 200),
        ]);

        $this->actingAs($user)
            ->get('/settings/social-traffic')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/SocialTraffic')
                ->has('socialAccounts', 1)
                ->where('socialAccounts.0.platform', 'youtube')
            );

        $this->assertSame(1, SocialAccount::where('user_id', $user->id)->count());
    }
}
