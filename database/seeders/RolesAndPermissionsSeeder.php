<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['FE', 'Bundle'] as $roleName) {
            Role::query()->firstOrCreate(['name' => $roleName]);
        }

        foreach (['view_app_features', 'view_extra_features'] as $permissionName) {
            Permission::query()->firstOrCreate(['name' => $permissionName]);
        }

        Role::query()->where('name', 'FE')->first()?->syncPermissions([
            'view_app_features',
        ]);

        Role::query()->where('name', 'Bundle')->first()?->syncPermissions([
            'view_app_features',
            'view_extra_features',
        ]);
    }
}
