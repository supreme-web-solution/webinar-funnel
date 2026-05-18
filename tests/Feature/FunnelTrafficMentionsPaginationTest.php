<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Keyword;
use App\Models\Mention;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelTrafficMentionsPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_funnel_edit_paginates_traffic_mentions(): void
    {
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
        ]);

        $keyword = Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'brand',
            'is_active' => true,
            'email_notifications' => false,
            'platforms' => ['reddit'],
        ]);

        for ($i = 0; $i < 12; $i++) {
            Mention::query()->create([
                'keyword_id' => $keyword->id,
                'user_id' => $user->id,
                'post_id' => 'post_'.$i,
                'title' => "Mention {$i}",
                'content' => 'Body',
                'source' => 'test',
                'source_type' => 'reddit',
                'status' => 'new',
                'posted_at' => now()->subMinutes($i),
            ]);
        }

        $this->actingAs($user)
            ->get(route('funnels.edit', ['funnel' => $funnel, 'page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('traffic.mentions.data', 2)
                ->where('traffic.mentions.current_page', 2)
                ->where('traffic.mentions.last_page', 2)
                ->where('traffic.mentions.total', 12));
    }
}
