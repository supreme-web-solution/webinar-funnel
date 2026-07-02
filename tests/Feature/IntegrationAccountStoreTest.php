<?php

namespace Tests\Feature;

use App\Models\IntegrationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationAccountStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_webhook_can_be_saved_without_auth_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/integrations', [
            'provider' => 'generic_webhook',
            'name' => 'Pabbly',
            'credentials' => [
                'webhook_url' => 'https://connect.pabbly.com/webhook-listener/webhook/example',
            ],
            'config' => [],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('integration_accounts', [
            'user_id' => $user->id,
            'provider' => 'generic_webhook',
            'name' => 'Pabbly',
        ]);

        $account = IntegrationAccount::query()->where('user_id', $user->id)->first();

        $this->assertSame(
            'https://connect.pabbly.com/webhook-listener/webhook/example',
            $account->credentials['webhook_url'] ?? null,
        );
        $this->assertArrayNotHasKey('api_key', $account->credentials);
    }

    public function test_generic_webhook_accepts_optional_auth_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/integrations', [
            'provider' => 'generic_webhook',
            'name' => 'Secure Hook',
            'credentials' => [
                'webhook_url' => 'https://example.com/hook',
                'api_key' => 'secret-token',
            ],
            'config' => [],
        ])->assertRedirect();

        $account = IntegrationAccount::query()->where('user_id', $user->id)->first();

        $this->assertSame('secret-token', $account->credentials['api_key'] ?? null);
    }
}
