<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelTrafficAiSettingsSaveVariantsTest extends TestCase
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
            'traffic_ai_reply_enabled' => false,
        ]);

        return $funnel;
    }

    public function test_invalid_link_override_blocks_save(): void
    {
        $user = User::factory()->create();
        $funnel = $this->funnelFor($user);

        $this->actingAs($user)
            ->from(route('funnels.edit', $funnel))
            ->patch(route('funnels.settings.update', $funnel), [
                'chat_mode' => 'simulated',
                'allow_replay' => true,
                'traffic_ai_reply_enabled' => true,
                'traffic_ai_link_override' => 'not-a-valid-url',
                'integration_account_ids' => [],
            ])
            ->assertSessionHasErrors('traffic_ai_link_override');

        $this->assertFalse((bool) $funnel->fresh()->settings?->traffic_ai_reply_enabled);
    }

    public function test_traffic_ai_only_fields_without_social_accounts_still_saves(): void
    {
        $user = User::factory()->create();
        $funnel = $this->funnelFor($user);

        $this->actingAs($user)
            ->patch(route('funnels.settings.update', $funnel), [
                'chat_mode' => 'simulated',
                'allow_replay' => true,
                'traffic_ai_reply_enabled' => true,
                'traffic_ai_link_override' => '',
                'traffic_ai_extra_context' => 'Contest $500',
                'integration_account_ids' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) $funnel->fresh()->settings?->traffic_ai_reply_enabled);
    }

    public function test_traffic_only_patch_without_integration_ids_does_not_wipe_integrations(): void
    {
        $user = User::factory()->create();
        $funnel = $this->funnelFor($user);

        $this->actingAs($user)
            ->patch(route('funnels.settings.update', $funnel), [
                'chat_mode' => 'simulated',
                'allow_replay' => true,
                'traffic_ai_reply_enabled' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) $funnel->fresh()->settings?->traffic_ai_reply_enabled);
    }
}
