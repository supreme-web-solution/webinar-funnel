<?php

namespace Tests\Feature;

use App\Models\Keyword;
use App\Models\Mention;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\TrafficAi\TrafficReplyPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZernioTrafficReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_posts_via_zernio_inbox_api(): void
    {
        config([
            'services.zernio.api_key' => 'test-key',
            'services.zernio.enabled' => true,
            'services.zernio.base_url' => 'https://zernio.com/api',
        ]);

        Http::fake([
            'zernio.com/api/v1/inbox/comments/tweet123' => Http::response([
                'success' => true,
                'data' => ['commentId' => 'reply_abc'],
            ], 200),
        ]);

        $user = User::factory()->create();
        $keyword = Keyword::query()->create([
            'user_id' => $user->id,
            'name' => 'brand',
            'is_active' => true,
            'email_notifications' => false,
            'platforms' => ['twitter'],
        ]);
        $account = SocialAccount::query()->create([
            'user_id' => $user->id,
            'platform' => 'twitter',
            'zernio_account_id' => 'acct_1',
            'daily_post_limit' => 50,
        ]);

        $mention = Mention::query()->create([
            'keyword_id' => $keyword->id,
            'user_id' => $user->id,
            'post_id' => 'tweet123',
            'title' => 'Hello brand',
            'content' => 'Hello brand',
            'source_type' => 'Twitter',
        ]);

        $result = app(TrafficReplyPoster::class)->post($account, $mention, 'Thanks for the shout-out!');

        $this->assertTrue($result['success']);
        $this->assertSame('reply_abc', $result['external_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://zernio.com/api/v1/inbox/comments/tweet123'
                && $request['accountId'] === 'acct_1'
                && $request['message'] === 'Thanks for the shout-out!';
        });
    }
}
