<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

final class UserRoleAssigner
{
    public const DEFAULT_ROLE = 'FE';

    /** @var list<string> */
    public const ASSIGNABLE_ROLES = ['FE', 'Bundle', 'Reseller', 'Affiliate Campaign Vault', 'Profit Multiplier'];

    public function rolesEnabled(): bool
    {
        if (! Schema::hasTable('roles')) {
            return false;
        }

        try {
            return Role::query()->where('name', self::DEFAULT_ROLE)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        if (! $this->rolesEnabled()) {
            return ['view_app_features'];
        }

        if ($user->roles()->count() === 0) {
            return ['view_app_features'];
        }

        return $user->getAllPermissions()->pluck('name')->all();
    }

    public function assignDefaultRole(User $user): void
    {
        if (! $this->rolesEnabled() || $user->roles()->count() > 0) {
            return;
        }

        $user->assignRole(self::DEFAULT_ROLE);
    }

    /**
     * @param  list<string>  $roles
     */
    public function syncRoles(User $user, array $roles): void
    {
        if (! $this->rolesEnabled()) {
            return;
        }

        $user->syncRoles(array_values(array_unique(array_filter($roles, fn ($role): bool => is_string($role) && $role !== ''))));
    }

    /**
     * @return list<string>
     */
    public function assignableRoles(): array
    {
        if (! $this->rolesEnabled()) {
            return [];
        }

        return collect(self::ASSIGNABLE_ROLES)
            ->filter(fn (string $name): bool => Role::query()->where('name', $name)->exists())
            ->values()
            ->all();
    }
}
