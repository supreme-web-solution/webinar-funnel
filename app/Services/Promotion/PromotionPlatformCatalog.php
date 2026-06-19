<?php

namespace App\Services\Promotion;

use App\Models\SocialAccount;
use Illuminate\Support\Collection;

final class PromotionPlatformCatalog
{
    /**
     * @return list<string>
     */
    public function supportedPlatforms(): array
    {
        return array_values(array_unique(array_map(
            'strval',
            (array) config('promotion.supported_platforms', [])
        )));
    }

    /**
     * Connected Zernio accounts the user can publish promotion posts to.
     *
     * @return list<array{platform: string, username: string|null, social_account_id: int}>
     */
    public function connectedForUser(int $userId): array
    {
        $supported = $this->supportedPlatforms();

        return SocialAccount::query()
            ->where('user_id', $userId)
            ->whereIn('platform', $supported)
            ->whereNotNull('zernio_account_id')
            ->where('zernio_account_id', '!=', '')
            ->orderBy('platform')
            ->get(['id', 'platform', 'platform_username'])
            ->map(fn (SocialAccount $account): array => [
                'platform' => (string) $account->platform,
                'username' => is_string($account->platform_username) && $account->platform_username !== ''
                    ? $account->platform_username
                    : null,
                'social_account_id' => (int) $account->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function connectedPlatformKeys(int $userId): array
    {
        return array_values(array_map(
            fn (array $row): string => $row['platform'],
            $this->connectedForUser($userId)
        ));
    }

    /**
     * @param  list<string>  $extra
     * @return list<string>
     */
    public function platformKeysForSelection(int $userId, array $extra = []): array
    {
        return array_values(array_unique(array_merge(
            $this->connectedPlatformKeys($userId),
            array_filter($extra, fn (mixed $p): bool => is_string($p) && $p !== '')
        )));
    }

    public function label(string $platform): string
    {
        return match ($platform) {
            'twitter' => 'X (Twitter)',
            'youtube' => 'YouTube',
            'reddit' => 'Reddit',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'linkedin' => 'LinkedIn',
            'pinterest' => 'Pinterest',
            default => ucfirst($platform),
        };
    }

    /**
     * @return Collection<int, SocialAccount>
     */
    public function connectedAccounts(int $userId): Collection
    {
        return SocialAccount::query()
            ->where('user_id', $userId)
            ->whereIn('platform', $this->supportedPlatforms())
            ->whereNotNull('zernio_account_id')
            ->where('zernio_account_id', '!=', '')
            ->orderBy('platform')
            ->get();
    }
}
