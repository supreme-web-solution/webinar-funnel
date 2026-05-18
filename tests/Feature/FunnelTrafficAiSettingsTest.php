<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelTrafficAiSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_traffic_ai_reply_enabled_persists_with_empty_link_override(): void
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
            'traffic_ai_reply_enabled' => false,
        ]);

        $response = $this->actingAs($user)->patch(route('funnels.settings.update', $funnel), [
            'chat_mode' => 'simulated',
            'allow_replay' => true,
            'traffic_ai_reply_enabled' => true,
            'traffic_ai_link_override' => '',
            'traffic_ai_extra_context' => '',
            'traffic_ai_social_account_ids' => [
                'reddit' => null,
                'youtube' => null,
                'twitter' => null,
            ],
            'integration_account_ids' => [],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertTrue(
            (bool) $funnel->fresh()->settings?->traffic_ai_reply_enabled,
            'traffic_ai_reply_enabled should be saved as true'
        );
    }
}
