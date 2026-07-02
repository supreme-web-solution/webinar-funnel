<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\ProductTableSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class JvzooWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            ProductTableSeeder::class,
        ]);

        config(['jvzoo.secret_key' => 'test-secret-key']);
    }

    public function test_sale_creates_user_and_assigns_role(): void
    {
        Mail::fake();

        $payload = $this->signedPayload([
            'ctransaction' => 'SALE',
            'ccustemail' => 'buyer@example.com',
            'ctransreceipt' => 'TX-123',
            'cproditem' => '444707',
        ]);

        $response = $this->post('/ipn/jvzoo', $payload);

        $response->assertOk()
            ->assertJson(['message' => 'User created successfully!']);

        $user = User::query()->where('email', 'buyer@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('FE'));
    }

    public function test_bundle_product_assigns_bundle_role(): void
    {
        Mail::fake();

        $payload = $this->signedPayload([
            'ctransaction' => 'SALE',
            'ccustemail' => 'bundle@example.com',
            'ctransreceipt' => 'TX-456',
            'cproditem' => (string) Product::query()->where('funnel', 'Bundle')->value('product_id'),
        ]);

        $this->post('/ipn/jvzoo', $payload)->assertOk();

        $user = User::query()->where('email', 'bundle@example.com')->first();

        $this->assertTrue($user->hasRole('Bundle'));
        $this->assertTrue($user->can('view_extra_features'));
    }

    public function test_refund_revokes_user_roles(): void
    {
        Mail::fake();

        $salePayload = $this->signedPayload([
            'ctransaction' => 'SALE',
            'ccustemail' => 'refund@example.com',
            'ctransreceipt' => 'TX-789',
            'cproditem' => '444707',
        ]);

        $this->post('/ipn/jvzoo', $salePayload)->assertOk();

        $refundPayload = $this->signedPayload([
            'ctransaction' => 'RFND',
            'ccustemail' => 'refund@example.com',
            'ctransreceipt' => 'TX-789-R',
            'cproditem' => '444707',
        ]);

        $this->post('/ipn/jvzoo', $refundPayload)
            ->assertOk()
            ->assertJson(['message' => 'User access revoked successfully!']);

        $user = User::query()->where('email', 'refund@example.com')->first();

        $this->assertSame(0, $user->roles()->count());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $response = $this->post('/ipn/jvzoo', [
            'ctransaction' => 'SALE',
            'ccustemail' => 'buyer@example.com',
            'ctransreceipt' => 'TX-000',
            'cproditem' => '444707',
            'cverify' => 'INVALID',
        ]);

        $response->assertForbidden();
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    private function signedPayload(array $fields): array
    {
        ksort($fields);

        $pop = '';

        foreach ($fields as $value) {
            $pop .= $value.'|';
        }

        $pop .= config('jvzoo.secret_key');

        $fields['cverify'] = strtoupper(substr(sha1($pop), 0, 8));

        return $fields;
    }
}
