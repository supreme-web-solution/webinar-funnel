<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Keyword;
use App\Models\Mention;
use App\Models\Template;
use App\Models\User;
use App\Services\Mentions\KeywordMentionCapEnforcer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelTrafficKeywordLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function funnelFor(User $user): Funnel
    {
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

        return $funnel;
    }

    public function test_cannot_add_more_than_max_keywords_per_funnel(): void
    {
        config(['limits.mentions.max_keywords_per_funnel' => 2]);

        $user = User::factory()->create();
        $funnel = $this->funnelFor($user);

        Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'one',
            'is_active' => true,
            'platforms' => ['reddit'],
        ]);
        Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'two',
            'is_active' => true,
            'platforms' => ['reddit'],
        ]);

        $response = $this->actingAs($user)->post(route('funnels.traffic.keywords.store', $funnel), [
            'name' => 'three',
            'platforms' => ['reddit'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('name');
        $this->assertSame(2, $funnel->keywords()->count());
    }

    public function test_keyword_is_auto_paused_when_mention_cap_reached(): void
    {
        config(['limits.mentions.max_mentions_per_keyword' => 3]);

        $user = User::factory()->create();
        $funnel = $this->funnelFor($user);

        $keyword = Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'brand',
            'is_active' => true,
            'platforms' => ['reddit'],
        ]);

        for ($i = 0; $i < 3; $i++) {
            Mention::query()->create([
                'keyword_id' => $keyword->id,
                'user_id' => $user->id,
                'post_id' => 'p_'.$i,
                'title' => 'M'.$i,
                'content' => 'Body',
                'source' => 'test',
                'source_type' => 'reddit',
                'status' => 'new',
            ]);
        }

        app(KeywordMentionCapEnforcer::class)->enforceCap($keyword->fresh());

        $keyword->refresh();
        $this->assertFalse($keyword->is_active);
    }

    public function test_cannot_re_enable_keyword_while_at_mention_cap(): void
    {
        config(['limits.mentions.max_mentions_per_keyword' => 1]);

        $user = User::factory()->create();
        $funnel = $this->funnelFor($user);

        $keyword = Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'brand',
            'is_active' => false,
            'platforms' => ['reddit'],
        ]);

        Mention::query()->create([
            'keyword_id' => $keyword->id,
            'user_id' => $user->id,
            'post_id' => 'p_1',
            'title' => 'M',
            'content' => 'Body',
            'source' => 'test',
            'source_type' => 'reddit',
            'status' => 'new',
        ]);

        $response = $this->actingAs($user)->patch(
            route('funnels.traffic.keywords.update', [$funnel, $keyword]),
            ['is_active' => true],
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('is_active');
        $this->assertFalse($keyword->fresh()->is_active);
    }
}
