<?php

namespace Tests\Unit;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\TrafficAi\TrafficSocialAccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrafficSocialAccountResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_default_account_when_map_is_empty(): void
    {
        $user = User::factory()->create();

        $account = SocialAccount::query()->create([
            'user_id' => $user->id,
            'platform' => 'reddit',
            'zernio_account_id' => 'zernio_1',
            'daily_post_limit' => 50,
        ]);

        $resolved = app(TrafficSocialAccountResolver::class)->resolveForPlatform($user->id, 'reddit', []);

        $this->assertNotNull($resolved);
        $this->assertSame($account->id, $resolved->id);
    }

    public function test_normalize_map_fills_single_accounts_per_platform(): void
    {
        $user = User::factory()->create();

        $reddit = SocialAccount::query()->create([
            'user_id' => $user->id,
            'platform' => 'reddit',
            'zernio_account_id' => 'z_r',
            'daily_post_limit' => 50,
        ]);

        $map = app(TrafficSocialAccountResolver::class)->normalizeMapForUser($user->id, [
            'reddit' => null,
            'youtube' => null,
            'twitter' => null,
        ]);

        $this->assertSame($reddit->id, $map['reddit']);
        $this->assertNull($map['youtube']);
        $this->assertNull($map['twitter']);
    }
}
