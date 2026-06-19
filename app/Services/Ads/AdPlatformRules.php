<?php

namespace App\Services\Ads;

/**
 * Zernio POST /v1/ads/create platform capabilities per official API docs.
 */
final class AdPlatformRules
{
    /** @var list<string> */
    public const ZERNIO_CREATE_PLATFORMS = [
        'facebook',
        'instagram',
        'tiktok',
        'google',
        'x',
        'linkedin',
        'pinterest',
    ];

    /** @var list<string> */
    public const NOT_STANDALONE_CREATE = [
        'reddit',
        'youtube',
    ];

    /** @var list<string> */
    public const META_PLATFORMS = ['facebook', 'instagram'];

    /** @var list<string> */
    public const LINKEDIN_GOALS = ['engagement', 'traffic', 'awareness', 'video_views'];

    public static function supportsStandaloneCreate(string $platform): bool
    {
        return in_array($platform, self::ZERNIO_CREATE_PLATFORMS, true);
    }

    public static function isMeta(string $platform): bool
    {
        return in_array($platform, self::META_PLATFORMS, true);
    }

    /**
     * @return list<string>
     */
    public static function unsupportedInSelection(array $platforms): array
    {
        return array_values(array_filter(
            $platforms,
            fn (string $p) => in_array($p, self::NOT_STANDALONE_CREATE, true)
        ));
    }

    /**
     * @return list<string>
     */
    public static function launchableFromSelection(array $platforms): array
    {
        return array_values(array_filter(
            $platforms,
            fn (string $p) => self::supportsStandaloneCreate($p)
        ));
    }

    public static function platformLabel(string $platform): string
    {
        return match ($platform) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'google' => 'Google Ads',
            'x' => 'X / Twitter',
            'linkedin' => 'LinkedIn',
            'pinterest' => 'Pinterest',
            'reddit' => 'Reddit',
            'youtube' => 'YouTube',
            default => ucfirst($platform),
        };
    }
}
