<?php

namespace Tests\Feature\Funnels;

use App\Jobs\DispatchLeadToEspJob;
use App\Models\Funnel;
use App\Models\FunnelIntegration;
use App\Models\IntegrationAccount;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FunnelFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_funnel_from_template(): void
    {
        $user = User::factory()->create(['username' => 'creator_user']);
        $this->actingAs($user);

        $template = Template::query()->create([
            'name' => 'Template A',
            'slug' => 'template-a',
            'category' => 'business',
            'conversion_style' => 'high-ticket',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        TemplateVersion::query()->create([
            'template_id' => $template->id,
            'version' => 1,
            'optin_schema' => ['hero' => ['headline' => 'H1']],
            'webinar_schema' => ['title' => 'W1'],
            'default_settings' => ['chat_mode' => 'simulated', 'allow_replay' => true, 'double_opt_in' => false],
            'is_current' => true,
        ]);

        $response = $this->post(route('funnels.store'), [
            'template_id' => $template->id,
            'name' => 'My First Funnel',
            'slug' => 'my-first-funnel',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('funnels', [
            'user_id' => $user->id,
            'slug' => 'my-first-funnel',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('funnel_pages', [
            'page_type' => 'optin',
        ]);

        $this->assertDatabaseHas('funnel_pages', [
            'page_type' => 'webinar',
        ]);
    }

    public function test_public_optin_captures_lead_and_queues_dispatch(): void
    {
        Queue::fake();

        $user = User::factory()->create(['username' => 'owner_user']);
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
            'name' => 'Launch Funnel',
            'slug' => 'launch-funnel',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $funnel->pages()->create([
            'page_type' => 'optin',
            'schema' => ['hero' => ['headline' => 'Join']],
            'version' => 1,
            'published_at' => now(),
        ]);

        $funnel->pages()->create([
            'page_type' => 'webinar',
            'schema' => ['title' => 'Webinar'],
            'version' => 1,
            'published_at' => now(),
        ]);

        $integration = IntegrationAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'generic_webhook',
            'name' => 'Webhook 1',
            'credentials' => ['webhook_url' => 'https://example.com/hook'],
            'status' => 'active',
        ]);

        FunnelIntegration::query()->create([
            'funnel_id' => $funnel->id,
            'integration_account_id' => $integration->id,
            'provider_list_config' => [],
            'enabled' => true,
        ]);

        $response = $this->post(route('public.optin.submit', [
            'username' => $user->username,
            'slug' => $funnel->slug,
        ]), [
            'name' => 'Lead One',
            'email' => 'lead@example.com',
        ]);

        $response->assertRedirect(route('public.webinar', [
            'username' => $user->username,
            'slug' => $funnel->slug,
        ], false));

        $this->assertDatabaseHas('leads', [
            'funnel_id' => $funnel->id,
            'email' => 'lead@example.com',
        ]);

        Queue::assertPushed(DispatchLeadToEspJob::class);
    }
}
