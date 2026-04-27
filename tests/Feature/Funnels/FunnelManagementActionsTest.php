<?php

namespace Tests\Feature\Funnels;

use App\Models\Funnel;
use App\Models\FunnelIntegration;
use App\Models\IntegrationAccount;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelManagementActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_unpublish_archive_and_delete_funnel(): void
    {
        $user = User::factory()->create(['username' => 'owner_actions']);
        $this->actingAs($user);

        $template = Template::query()->create([
            'name' => 'Template A',
            'slug' => 'template-a',
            'category' => 'business',
            'conversion_style' => 'high-ticket',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'Manage Me',
            'slug' => 'manage-me',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $funnel->pages()->createMany([
            ['page_type' => 'optin', 'schema' => ['html' => '<div>optin</div>'], 'version' => 1, 'published_at' => now()],
            ['page_type' => 'webinar', 'schema' => ['title' => 'room'], 'version' => 1, 'published_at' => now()],
        ]);

        $this->post(route('funnels.unpublish', $funnel))->assertRedirect();
        $this->assertDatabaseHas('funnels', [
            'id' => $funnel->id,
            'status' => 'draft',
            'published_at' => null,
        ]);

        $this->post(route('funnels.archive', $funnel))->assertRedirect();
        $this->assertDatabaseHas('funnels', [
            'id' => $funnel->id,
            'status' => 'archived',
            'published_at' => null,
        ]);

        $this->delete(route('funnels.destroy', $funnel))->assertRedirect(route('funnels.index'));
        $this->assertDatabaseMissing('funnels', ['id' => $funnel->id]);
    }

    public function test_settings_update_keeps_existing_provider_config_when_reselecting_accounts(): void
    {
        $user = User::factory()->create(['username' => 'owner_configs']);
        $this->actingAs($user);

        $template = Template::query()->create([
            'name' => 'Template B',
            'slug' => 'template-b',
            'category' => 'business',
            'conversion_style' => 'lead-gen',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'Config Funnel',
            'slug' => 'config-funnel',
            'status' => 'draft',
        ]);

        $funnel->settings()->create([
            'chat_mode' => 'simulated',
            'allow_replay' => true,
        ]);

        $integration = IntegrationAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'mailchimp',
            'name' => 'Mailchimp A',
            'credentials' => ['api_key' => 'abc-us1', 'audience_id' => 'aud-1'],
            'status' => 'active',
        ]);

        FunnelIntegration::query()->create([
            'funnel_id' => $funnel->id,
            'integration_account_id' => $integration->id,
            'provider_list_config' => ['audience_id' => 'aud-1', 'tag' => 'vip'],
            'enabled' => true,
        ]);

        $this->patch(route('funnels.settings.update', $funnel), [
            'chat_mode' => 'simulated',
            'allow_replay' => true,
            'integration_account_ids' => [$integration->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('funnel_integrations', [
            'funnel_id' => $funnel->id,
            'integration_account_id' => $integration->id,
            'provider_list_config->audience_id' => 'aud-1',
            'provider_list_config->tag' => 'vip',
        ]);
    }
}
