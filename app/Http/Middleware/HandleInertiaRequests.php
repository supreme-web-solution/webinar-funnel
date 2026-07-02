<?php

namespace App\Http\Middleware;

use App\Services\Auth\UserRoleAssigner;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $adminEmails = collect(explode(',', (string) env('ADMIN_EMAILS', '')))
            ->map(fn ($item) => strtolower(trim($item)))
            ->filter()
            ->values();
        $currentEmail = strtolower((string) optional($request->user())->email);
        $user = $request->user();
        $permissions = $user !== null
            ? app(UserRoleAssigner::class)->permissionsFor($user)
            : [];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'appearanceDarkModeEnabled' => (bool) config('appearance.dark_mode_enabled'),
            'paidAdsEnabled' => (bool) config('promotion.ads.enabled'),
            'auth' => [
                'user' => $user,
                'is_admin' => $currentEmail !== '' && $adminEmails->contains($currentEmail),
                'permissions' => $permissions,
                'can_view_app_features' => in_array('view_app_features', $permissions, true),
                'can_view_bundle_features' => in_array('view_extra_features', $permissions, true),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
