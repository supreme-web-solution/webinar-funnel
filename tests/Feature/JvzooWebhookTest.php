<?php

namespace Tests\Feature;

use App\Mail\WelcomeMail;
use App\Models\User;
use Database\Seeders\JvzooAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JvzooWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_PERMISSIONS = [
        'view_app_features',
        'access_reseller',
        'access_affiliate_campaign_vault',
        'access_profit_multiplier',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(JvzooAccessSeeder::class);

        config(['jvzoo.secret_key' => 'test-secret-key']);
    }

    public function test_sale_creates_user_and_assigns_role(): void
    {
        Mail::fake();

        $response = $this->post('/ipn/jvzoo', $this->sale('buyer@example.com', '455425'));

        $response->assertOk()
            ->assertJson([
                'message' => 'User created successfully!',
                'email_sent' => true,
            ]);

        $user = User::query()->where('email', 'buyer@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('FE'));
        $this->assertTrue($user->can('view_app_features'));
        $this->assertFalse($user->can('access_reseller'));
        $this->assertFalse($user->can('access_affiliate_campaign_vault'));
        $this->assertFalse($user->can('access_profit_multiplier'));

        Mail::assertSent(WelcomeMail::class, function (WelcomeMail $mail): bool {
            return $mail->hasTo('buyer@example.com')
                && $mail->user->email === 'buyer@example.com'
                && strlen($mail->password) >= 8;
        });
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function productAccessProvider(): array
    {
        return [
            'FE' => ['455425', 'FE', ['view_app_features']],
            'Bundle' => ['455427', 'Bundle', self::ALL_PERMISSIONS],
            'Fast-pass bundle 1' => ['456171', 'Bundle', self::ALL_PERMISSIONS],
            'Fast-pass bundle 2' => ['456173', 'Bundle', self::ALL_PERMISSIONS],
            'Reseller 1' => ['456225', 'Reseller', ['access_reseller']],
            'Reseller 2' => ['456227', 'Reseller', ['access_reseller']],
            'Affiliate Campaign Vault 1' => ['456183', 'Affiliate Campaign Vault', ['access_affiliate_campaign_vault']],
            'Affiliate Campaign Vault 2' => ['456187', 'Affiliate Campaign Vault', ['access_affiliate_campaign_vault']],
            'Profit Multiplier 1' => ['456221', 'Profit Multiplier', ['access_profit_multiplier']],
            'Profit Multiplier 2' => ['456223', 'Profit Multiplier', ['access_profit_multiplier']],
        ];
    }

    /**
     * @param  list<string>  $expectedPermissions
     */
    #[DataProvider('productAccessProvider')]
    public function test_each_product_grants_the_right_access(string $productId, string $role, array $expectedPermissions): void
    {
        Mail::fake();

        $this->post('/ipn/jvzoo', $this->sale('product@example.com', $productId))->assertOk();

        $user = User::query()->where('email', 'product@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole($role));

        foreach (self::ALL_PERMISSIONS as $permission) {
            $this->assertSame(
                in_array($permission, $expectedPermissions, true),
                $user->can($permission),
                "Product {$productId} has wrong value for {$permission}",
            );
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function oldProductProvider(): array
    {
        return [
            '444707' => ['444707'],
            '444709' => ['444709'],
            '445139' => ['445139'],
            '445141' => ['445141'],
        ];
    }

    #[DataProvider('oldProductProvider')]
    public function test_old_product_ids_are_rejected(string $productId): void
    {
        $this->post('/ipn/jvzoo', $this->sale('old@example.com', $productId))->assertNotFound();

        $this->assertNull(User::query()->where('email', 'old@example.com')->first());
    }

    public function test_add_on_purchase_keeps_existing_access(): void
    {
        Mail::fake();

        $this->post('/ipn/jvzoo', $this->sale('stack@example.com', '455425'))->assertOk();
        $this->post('/ipn/jvzoo', $this->sale('stack@example.com', '456225', 'TX-2'))
            ->assertOk()
            ->assertJson(['message' => 'User role updated successfully!']);

        $user = User::query()->where('email', 'stack@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('FE'));
        $this->assertTrue($user->hasRole('Reseller'));
        $this->assertTrue($user->can('view_app_features'));
        $this->assertTrue($user->can('access_reseller'));
        Mail::assertSentCount(1);
    }

    public function test_refund_only_revokes_the_refunded_product_role(): void
    {
        Mail::fake();

        $this->post('/ipn/jvzoo', $this->sale('refund@example.com', '455425'))->assertOk();
        $this->post('/ipn/jvzoo', $this->sale('refund@example.com', '456221', 'TX-PM'))->assertOk();

        $this->post('/ipn/jvzoo', $this->signedPayload([
            'ctransaction' => 'RFND',
            'ccustemail' => 'refund@example.com',
            'ctransreceipt' => 'TX-PM-R',
            'cproditem' => '456221',
        ]))
            ->assertOk()
            ->assertJson(['message' => 'User access revoked successfully!']);

        $user = User::query()->where('email', 'refund@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('FE'));
        $this->assertFalse($user->hasRole('Profit Multiplier'));
        $this->assertFalse($user->can('access_profit_multiplier'));
    }

    public function test_refund_of_only_product_removes_all_roles(): void
    {
        Mail::fake();

        $this->post('/ipn/jvzoo', $this->sale('refund-fe@example.com', '455425'))->assertOk();

        $this->post('/ipn/jvzoo', $this->signedPayload([
            'ctransaction' => 'RFND',
            'ccustemail' => 'refund-fe@example.com',
            'ctransreceipt' => 'TX-FE-R',
            'cproditem' => '455425',
        ]))->assertOk();

        $user = User::query()->where('email', 'refund-fe@example.com')->firstOrFail();

        $this->assertSame(0, $user->roles()->count());
    }

    public function test_sale_accepts_json_payload(): void
    {
        Mail::fake();

        $this->postJson('/ipn/jvzoo', $this->sale('json-buyer@example.com', '455425'))
            ->assertOk()
            ->assertJson(['message' => 'User created successfully!']);

        $this->assertNotNull(User::query()->where('email', 'json-buyer@example.com')->first());
    }

    public function test_sale_still_succeeds_when_welcome_email_fails(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('Resend API error'));

        $this->post('/ipn/jvzoo', $this->sale('mail-fail@example.com', '455425'))
            ->assertOk()
            ->assertJson([
                'email_sent' => false,
            ]);

        $this->assertNotNull(User::query()->where('email', 'mail-fail@example.com')->first());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $response = $this->post('/ipn/jvzoo', [
            'ctransaction' => 'SALE',
            'ccustemail' => 'buyer@example.com',
            'ctransreceipt' => 'TX-000',
            'cproditem' => '455425',
            'cverify' => 'INVALID',
        ]);

        $response->assertForbidden();
    }

    /**
     * @return array<string, string>
     */
    private function sale(string $email, string $productId, string $transactionId = 'TX-1'): array
    {
        return $this->signedPayload([
            'ctransaction' => 'SALE',
            'ccustemail' => $email,
            'ctransreceipt' => $transactionId,
            'cproditem' => $productId,
        ]);
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
