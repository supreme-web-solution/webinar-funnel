<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Keyword;
use App\Models\Mention;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FunnelTrafficMentionDraftReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_draft_reply_for_reddit_mention(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Try this link https://example.com/offer']],
                ],
            ], 200),
        ]);

        config(['services.openai.api_key' => 'test-key']);

        $user = User::factory()->create();
        $template = Template::query()->create([
            'name' => 'T',
            'slug' => 't-'.uniqid(),
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'F',
            'slug' => 'f-'.uniqid(),
            'status' => 'draft',
        ]);
        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
            'affiliate_request_link' => 'https://example.com/offer',
        ]);

        $keyword = Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'brand',
            'is_active' => true,
            'email_notifications' => false,
            'platforms' => ['reddit'],
        ]);

        $mention = Mention::query()->create([
            'keyword_id' => $keyword->id,
            'user_id' => $user->id,
            'post_id' => 'post_1',
            'title' => 'Need help',
            'content' => 'Any tips?',
            'source' => 'test',
            'source_type' => 'reddit',
            'permalink' => 'https://reddit.com/r/test/comments/abc',
            'status' => 'new',
        ]);

        $this->actingAs($user)
            ->postJson(route('funnels.traffic.mentions.draft-reply', [
                'funnel' => $funnel,
                'mention' => $mention,
            ]))
            ->assertOk()
            ->assertJsonPath('platform', 'reddit')
            ->assertJsonPath('permalink', 'https://reddit.com/r/test/comments/abc')
            ->assertJsonStructure(['reply']);
    }
}
