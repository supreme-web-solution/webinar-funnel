<?php

namespace App\Services\Ads;

use App\Models\FunnelAdCampaign;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Zernio\ZernioClient;
use App\Services\Zernio\ZernioProfileManager;

/**
 * Resolves Zernio social account IDs for paid ads.
 *
 * Per Zernio docs, ads endpoints take:
 * - accountId  → connected social account in Zernio (resolved automatically)
 * - adAccountId  → platform ad account where media spend is billed (user-provided)
 */
final class AdAccountResolver
{
    /** @var array<string, list<string>> */
    private const PLATFORM_MATCHERS = [
        'facebook'  => ['facebook', 'meta', 'metaads'],
        'instagram' => ['instagram', 'facebook', 'meta', 'metaads'],
        'tiktok'    => ['tiktok', 'tiktokads'],
        'google'    => ['google', 'googleads'],
        'x'         => ['x', 'twitter', 'xads'],
        'linkedin'  => ['linkedin', 'linkedinads'],
        'pinterest' => ['pinterest', 'pinterestads'],
        'reddit'    => ['reddit'],
        'youtube'   => ['youtube', 'google', 'googleads'],
    ];

    public function __construct(
        private readonly ZernioClient $zernio,
        private readonly ZernioProfileManager $profiles,
    ) {}

    public function resolveSocialAccountId(FunnelAdCampaign $campaign, string $platform): ?string
    {
        $override = trim((string) ($campaign->zernio_social_account_id ?? ''));
        if ($override !== '') {
            return $override;
        }

        $fallback = trim((string) config('services.zernio.default_social_account_id', ''));
        if ($fallback !== '') {
            return $fallback;
        }

        $user = $campaign->user;
        if (! $user) {
            return null;
        }

        if (AdPlatformRules::isMeta($platform)) {
            $metaAdsAccount = $this->resolveMetaAdsAccountFromZernio($user);
            if ($metaAdsAccount !== null) {
                return $metaAdsAccount;
            }
        }

        $fromLocal = $this->resolveFromLocalSocialAccounts($user, $platform);
        if ($fromLocal !== null) {
            return $fromLocal;
        }

        return $this->resolveFromZernioProfile($user, $platform);
    }

    public function normalizeAdAccountId(string $platform, string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }

        if (in_array($platform, ['facebook', 'instagram'], true) && preg_match('/^\d+$/', $id)) {
            return 'act_'.$id;
        }

        return $id;
    }

    /**
     * Resolve billing currency for a platform ad account via Zernio.
     */
    public function resolveAdAccountCurrency(string $socialAccountId, string $adAccountId): ?string
    {
        if (! $this->zernio->isConfigured() || $socialAccountId === '' || $adAccountId === '') {
            return null;
        }

        try {
            $accounts = $this->zernio->listAdAccounts($socialAccountId, $adAccountId, 50);
        } catch (\Throwable) {
            return null;
        }

        $needle = strtolower(preg_replace('/^act_/i', '', $adAccountId) ?? $adAccountId);

        foreach ($accounts as $account) {
            if (! is_array($account)) {
                continue;
            }

            $candidates = array_filter([
                (string) ($account['adAccountId'] ?? ''),
                (string) ($account['id'] ?? ''),
                (string) ($account['account_id'] ?? ''),
                (string) ($account['_id'] ?? ''),
            ]);

            $matched = false;
            foreach ($candidates as $candidate) {
                $normalized = strtolower(preg_replace('/^act_/i', '', $candidate) ?? $candidate);
                if ($normalized !== '' && $normalized === $needle) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched && count($accounts) === 1) {
                $matched = true;
            }

            if (! $matched) {
                continue;
            }

            $currency = strtoupper(trim((string) ($account['currency'] ?? $account['currencyCode'] ?? '')));
            if ($currency !== '') {
                return $currency;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, string>
     */
    public static function normalisePlatformIds(array $incoming): array
    {
        $out = [];
        foreach ($incoming as $platform => $id) {
            if (! is_string($platform)) {
                continue;
            }
            $value = trim((string) $id);
            if ($value !== '') {
                $out[$platform] = $value;
            }
        }

        return $out;
    }

    private function resolveFromLocalSocialAccounts(User $user, string $platform): ?string
    {
        $matchers = self::PLATFORM_MATCHERS[$platform] ?? [$platform];

        $account = SocialAccount::query()
            ->where('user_id', $user->id)
            ->whereNotNull('zernio_account_id')
            ->whereIn('platform', $matchers)
            ->first();

        $id = $account?->zernio_account_id;

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function resolveFromZernioProfile(User $user, string $platform): ?string
    {
        if (! $this->zernio->isConfigured()) {
            return null;
        }

        try {
            $accounts = $this->profiles->withProfile(
                $user,
                fn (string $profileId): array => $this->zernio->listAccountsForProfile($profileId),
            );
        } catch (\Throwable) {
            return null;
        }

        $matchers = self::PLATFORM_MATCHERS[$platform] ?? [$platform];
        $fallbackId = null;

        foreach ($accounts as $account) {
            if (! is_array($account)) {
                continue;
            }

            $accountPlatform = strtolower(trim((string) ($account['platform'] ?? '')));
            if (! in_array($accountPlatform, $matchers, true)) {
                continue;
            }

            $id = (string) ($account['_id'] ?? $account['id'] ?? '');
            if ($id === '') {
                continue;
            }

            if ($accountPlatform === 'metaads') {
                return $id;
            }

            if ($fallbackId === null) {
                $fallbackId = $id;
            }
        }

        return $fallbackId;
    }

    /**
     * Zernio stores a dedicated metaads social account for Marketing API writes.
     */
    private function resolveMetaAdsAccountFromZernio(User $user): ?string
    {
        if (! $this->zernio->isConfigured()) {
            return null;
        }

        try {
            $accounts = $this->profiles->withProfile(
                $user,
                fn (string $profileId): array => $this->zernio->listAccountsForProfile($profileId),
            );
        } catch (\Throwable) {
            return null;
        }

        foreach ($accounts as $account) {
            if (! is_array($account)) {
                continue;
            }

            if (strtolower(trim((string) ($account['platform'] ?? ''))) !== 'metaads') {
                continue;
            }

            $id = (string) ($account['_id'] ?? $account['id'] ?? '');

            return $id !== '' ? $id : null;
        }

        return null;
    }
}
