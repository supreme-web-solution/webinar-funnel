<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\TrackedLink;
use App\Models\User;
use App\Services\Campaigns\TrackedLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TrackedLinkRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_hash_destination_is_repaired_from_campaign_affiliate_link(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'Test',
            'slug' => 'test-'.uniqid(),
            'type' => Campaign::TYPE_SALES,
            'status' => 'draft',
            'affiliate_link' => 'https://www.jvzoo.com/c/123/456',
        ]);

        $link = TrackedLink::query()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'code' => 'x4pm2bsa',
            'label' => 'Lead magnet footer CTA',
            'destination_url' => '#',
            'is_active' => true,
        ]);

        $url = app(TrackedLinkService::class)->resolveRedirect($link, Request::create('/r/x4pm2bsa', 'GET'));

        $this->assertSame('https://www.jvzoo.com/c/123/456', $url);
        $this->assertSame('https://www.jvzoo.com/c/123/456', $link->fresh()->destination_url);
    }

    public function test_self_referential_tracked_destination_does_not_loop(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'Test',
            'slug' => 'test-'.uniqid(),
            'type' => Campaign::TYPE_SALES,
            'status' => 'draft',
            'affiliate_link' => 'https://example.com/offer',
        ]);

        $link = TrackedLink::query()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'code' => 'loopcode',
            'label' => 'Affiliate offer',
            'destination_url' => url('/r/loopcode'),
            'is_active' => true,
        ]);

        $url = app(TrackedLinkService::class)->resolveRedirect($link, Request::create('/r/loopcode', 'GET'));

        $this->assertSame('https://example.com/offer', $url);
    }
}
