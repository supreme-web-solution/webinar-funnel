<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Role name => permissions. Role names must match the `funnel` column in ProductTableSeeder.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'FE' => [
            'view_app_features',
        ],
        'Bundle' => [
            'view_app_features',
            'access_reseller',
            'access_affiliate_campaign_vault',
            'access_profit_multiplier',
        ],
        'Reseller' => [
            'access_reseller',
        ],
        'Affiliate Campaign Vault' => [
            'access_affiliate_campaign_vault',
        ],
        'Profit Multiplier' => [
            'access_profit_multiplier',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionNames = collect(self::ROLE_PERMISSIONS)->flatten()->unique()->values();

        Permission::query()
            ->whereNotIn('name', $permissionNames)
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        foreach ($permissionNames as $permissionName) {
            Permission::query()->firstOrCreate(['name' => $permissionName]);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            Role::query()->firstOrCreate(['name' => $roleName])->syncPermissions($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
