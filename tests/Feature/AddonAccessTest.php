<?php

namespace Tests\Feature;

use App\Mail\WelcomeMail;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\JvzooAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AddonAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(JvzooAccessSeeder::class);
    }

    public function test_seeder_removes_old_products_and_permissions(): void
    {
        Product::query()->create(['product_id' => 444707, 'name' => 'Old', 'funnel' => 'FE']);
        Permission::query()->create(['name' => 'view_extra_features']);

        $this->seed(JvzooAccessSeeder::class);

        $this->assertDatabaseMissing('products', ['product_id' => 444707]);
        $this->assertDatabaseMissing('permissions', ['name' => 'view_extra_features']);
        $this->assertSame(10, Product::query()->count());
    }

    public function test_fe_user_cannot_open_add_on_pages(): void
    {
        $user = $this->userWithRoles(['FE']);

        $this->actingAs($user)->get('/reseller')->assertForbidden();
        $this->actingAs($user)->get('/affiliate-campaign-vault')->assertForbidden();
        $this->actingAs($user)->get('/profit-multiplier')->assertForbidden();
    }

    public function test_bundle_user_can_open_all_add_on_pages(): void
    {
        $user = $this->userWithRoles(['Bundle']);

        $this->actingAs($user)->get('/reseller')->assertOk();
        $this->actingAs($user)->get('/affiliate-campaign-vault')->assertOk();
        $this->actingAs($user)->get('/profit-multiplier')->assertOk();
    }

    public function test_add_on_buyer_only_opens_their_add_on_page(): void
    {
        $user = $this->userWithRoles(['FE', 'Affiliate Campaign Vault']);

        $this->actingAs($user)
            ->get('/affiliate-campaign-vault')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('affiliate-campaign-vault/Index')
                ->where('signupUrl', 'https://affilimachine.com/register'));

        $this->actingAs($user)->get('/reseller')->assertForbidden();
        $this->actingAs($user)->get('/profit-multiplier')->assertForbidden();
    }

    public function test_profit_multiplier_page_lists_configured_links(): void
    {
        config(['addons.profit_multiplier.links' => [
            ['label' => 'First', 'url' => 'https://example.com/one'],
            ['label' => 'Second', 'url' => null],
        ]]);

        $this->actingAs($this->userWithRoles(['Profit Multiplier']))
            ->get('/profit-multiplier')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('profit-multiplier/Index')
                ->where('links.0.url', 'https://example.com/one')
                ->where('links.1.url', null));
    }

    public function test_reseller_creates_fe_account_linked_to_them(): void
    {
        Mail::fake();

        $reseller = $this->userWithRoles(['FE', 'Reseller']);

        $this->actingAs($reseller)
            ->post('/reseller/accounts', ['name' => 'Jane Customer', 'email' => 'jane@example.com'])
            ->assertRedirect();

        $account = User::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertSame($reseller->id, $account->reseller_id);
        $this->assertSame(['FE'], $account->getRoleNames()->all());
        $this->assertFalse($account->can('access_reseller'));

        Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail): bool => $mail->hasTo('jane@example.com'));

        $this->actingAs($reseller)
            ->get('/reseller')
            ->assertInertia(fn ($page) => $page
                ->component('reseller/Index')
                ->where('stats.total', 1)
                ->where('accounts.data.0.email', 'jane@example.com'));
    }

    public function test_reseller_cannot_create_account_for_existing_email(): void
    {
        $reseller = $this->userWithRoles(['Reseller']);
        User::factory()->create(['email' => 'taken@example.com', 'username' => 'taken_user']);

        $this->actingAs($reseller)
            ->post('/reseller/accounts', ['name' => 'Taken', 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_reseller_can_delete_only_their_own_accounts(): void
    {
        Mail::fake();

        $reseller = $this->userWithRoles(['Reseller']);
        $otherReseller = $this->userWithRoles(['Reseller']);

        $this->actingAs($reseller)->post('/reseller/accounts', ['name' => 'Mine', 'email' => 'mine@example.com']);
        $this->actingAs($otherReseller)->post('/reseller/accounts', ['name' => 'Theirs', 'email' => 'theirs@example.com']);

        $mine = User::query()->where('email', 'mine@example.com')->firstOrFail();
        $theirs = User::query()->where('email', 'theirs@example.com')->firstOrFail();

        $this->actingAs($reseller)->delete("/reseller/accounts/{$theirs->id}")->assertNotFound();
        $this->assertNotNull($theirs->fresh());

        $this->actingAs($reseller)->delete("/reseller/accounts/{$mine->id}")->assertRedirect();
        $this->assertNull($mine->fresh());
    }

    /**
     * @param  list<string>  $roles
     */
    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create();
        $user->assignRole($roles);

        return $user;
    }
}
