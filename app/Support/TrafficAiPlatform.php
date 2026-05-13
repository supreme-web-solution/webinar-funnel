<?php

namespace App\Support;

final class TrafficAiPlatform
{
    /**
     * Normalize mention.source_type (e.g. "Reddit") to config keys (e.g. "reddit").
     */
    public static function fromMentionSource(?string $sourceType): ?string
    {
        if ($sourceType === null || $sourceType === '') {
            return null;
        }

        return match (strtolower(trim($sourceType))) {
            'reddit' => 'reddit',
            'youtube' => 'youtube',
            'twitter', 'x' => 'twitter',
            'news' => 'news',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function connectablePlatforms(): array
    {
        return ['reddit', 'youtube', 'twitter'];
    }
}
