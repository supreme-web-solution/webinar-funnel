<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\FunnelAdCampaign;
use App\Models\SocialAccount;
use App\Services\Ads\AdAccountResolver;
use App\Services\Zernio\ZernioClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdAccountSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $saved = AdAccountResolver::normalisePlatformIds(is_array($user->platform_ad_account_ids) ? $user->platform_ad_account_ids : []);

        if ($saved === [] && is_string($user->zernio_ad_account_id) && $user->zernio_ad_account_id !== '') {
            $saved['facebook'] = $user->zernio_ad_account_id;
        }

        if ($saved === []) {
            $latest = FunnelAdCampaign::query()
                ->where('user_id', $user->id)
                ->whereNotNull('platform_ad_account_ids')
                ->latest()
                ->first();
            if ($latest && is_array($latest->platform_ad_account_ids)) {
                $saved = AdAccountResolver::normalisePlatformIds($latest->platform_ad_account_ids);
            }
        }

        return Inertia::render('settings/AdAccounts', [
            'adPlatforms' => FunnelAdCampaign::AD_PLATFORMS,
            'savedAdAccountIds' => $saved,
            'suggestions' => $this->fetchSuggestions($user->id),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'platform_ad_account_ids' => ['required', 'array'],
            'platform_ad_account_ids.*' => ['nullable', 'string', 'max:120'],
        ]);

        $ids = AdAccountResolver::normalisePlatformIds(is_array($validated['platform_ad_account_ids'] ?? null) ? $validated['platform_ad_account_ids'] : []);

        $user->forceFill(['platform_ad_account_ids' => $ids])->save();

        return back()->with('success', 'Ad account IDs saved. They will pre-fill when you create new campaigns.');
    }

    /**
     * @return array<string, list<array{id: string, name: string|null}>>
     */
    private function fetchSuggestions(int $userId): array
    {
        $zernio = app(ZernioClient::class);
        if (! $zernio->isConfigured()) {
            return [];
        }

        $suggestions = [];
        $lookup = [
            'facebook' => ['facebook', 'meta', 'metaads'],
            'instagram' => ['instagram', 'facebook', 'meta'],
            'tiktok' => ['tiktok'],
            'linkedin' => ['linkedin'],
            'pinterest' => ['pinterest'],
            'google' => ['google', 'googleads', 'youtube'],
            'x' => ['twitter', 'x'],
        ];

        foreach ($lookup as $adPlatform => $socialPlatforms) {
            $account = SocialAccount::query()
                ->where('user_id', $userId)
                ->whereNotNull('zernio_account_id')
                ->whereIn('platform', $socialPlatforms)
                ->first();

            if (! $account?->zernio_account_id) {
                continue;
            }

            try {
                $rows = $zernio->listAdAccounts((string) $account->zernio_account_id, null, 20);
            } catch (\Throwable) {
                continue;
            }

            $mapped = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = (string) ($row['id'] ?? $row['ad_account_id'] ?? $row['account_id'] ?? $row['platformAdAccountId'] ?? '');
                if ($id === '') {
                    continue;
                }
                $name = $row['name'] ?? $row['account_name'] ?? $row['platformAdAccountName'] ?? null;
                $mapped[] = [
                    'id' => $id,
                    'name' => is_string($name) && $name !== '' ? $name : null,
                ];
            }

            if ($mapped !== []) {
                $suggestions[$adPlatform] = $mapped;
            }
        }

        return $suggestions;
    }
}
