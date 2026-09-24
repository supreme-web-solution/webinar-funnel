<?php

namespace App\Services\Content;

use App\Models\UserTrafficProfile;

/**
 * Picks optimal platform formats for Content Employee when auto-select is enabled.
 */
final class ContentEmployeeFormatSelectorService
{
    public function __construct(
        private readonly PlatformFormatCatalog $catalog,
    ) {}

    public function isAutoSelectEnabled(UserTrafficProfile $profile): bool
    {
        return (bool) ($profile->meta['auto_select_formats'] ?? false);
    }

    /**
     * Weekly post targets by intensity when auto-selecting formats.
     */
    public function weeklyPostTarget(string $intensity): int
    {
        return match ($intensity) {
            PlatformFormatCatalog::INTENSITY_STARTER => 12,
            PlatformFormatCatalog::INTENSITY_POWER => 35,
            default => 20,
        };
    }

    /**
     * Pick the best catalog format for a topic, preferring connected platforms and variety.
     *
     * @param  list<string>  $connectedPlatforms  Promotion platform keys (twitter, instagram, …)
     * @param  list<string>  $alreadyUsed  Format keys already picked this week
     */
    public function pickBestFormat(
        string $topic,
        UserTrafficProfile $profile,
        array $connectedPlatforms,
        array $alreadyUsed = [],
        array $allowedPlatforms = [],
    ): string {
        $topicLower = mb_strtolower($topic);
        $scores = [];
        $allowed = $allowedPlatforms !== [] ? $allowedPlatforms : $connectedPlatforms;

        foreach ($this->catalog->allFormats() as $key => $spec) {
            $platform = (string) ($spec['platform'] ?? '');
            $promoPlatform = $this->mapPlatformForPromotion($platform);

            if ($allowed !== [] && ! in_array($promoPlatform, $allowed, true)) {
                continue;
            }

            $score = 0.0;

            $score += $this->topicFormatAffinity($topicLower, $key, $spec);
            $score += (float) ($spec['frequency_per_week'] ?? 1) * 2;

            if ($connectedPlatforms !== [] && in_array($promoPlatform, $connectedPlatforms, true)) {
                $score += 15;
            } elseif ($connectedPlatforms === []) {
                $score += 5;
            } else {
                $score -= 8;
            }

            if (in_array($key, $alreadyUsed, true)) {
                $score -= 12;
            }

            $platformUsedCount = count(array_filter(
                $alreadyUsed,
                fn (string $usedKey): bool => ($this->catalog->format($usedKey)['platform'] ?? '') === $platform,
            ));
            $score -= $platformUsedCount * 4;

            $scores[$key] = $score;
        }

        arsort($scores);

        $best = array_key_first($scores);

        if (is_string($best) && $best !== '') {
            return $best;
        }

        foreach ($this->catalog->allFormatKeys() as $fallbackKey) {
            $spec = $this->catalog->format($fallbackKey);
            $platform = $this->mapPlatformForPromotion((string) ($spec['platform'] ?? ''));
            if ($allowed === [] || in_array($platform, $allowed, true)) {
                return $fallbackKey;
            }
        }

        return 'x_text_post';
    }

    /**
     * @param  list<string>  $topicSeeds
     * @param  list<string>  $connectedPlatforms
     * @return list<string> Format key per planned slot
     */
    public function formatsForAutoPlan(
        UserTrafficProfile $profile,
        array $topicSeeds,
        array $connectedPlatforms,
    ): array {
        $target = $this->weeklyPostTarget((string) $profile->intensity);
        $topics = $topicSeeds !== [] ? $topicSeeds : ['Affiliate marketing tips'];
        $picked = [];
        $used = [];

        for ($i = 0; $i < $target; $i++) {
            $topic = (string) $topics[$i % count($topics)];
            $format = $this->pickBestFormat($topic, $profile, $connectedPlatforms, $used);
            $picked[] = $format;
            $used[] = $format;
        }

        return $picked;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function topicFormatAffinity(string $topicLower, string $formatKey, array $spec): float
    {
        $score = 0.0;
        $generator = (string) ($spec['generator'] ?? '');
        $label = mb_strtolower((string) ($spec['label'] ?? ''));

        $rules = [
            'carousel' => ['mistake', 'mistakes', 'tips', 'ways', 'reasons', 'steps', 'guide', 'swipe', 'slides'],
            'thread' => ['thread', 'story', 'journey', 'lesson', 'breakdown', 'arc'],
            'reel_script' => ['hook', 'quick', 'secret', 'viral', 'watch', 'seconds', 'reel', 'short'],
            'reddit_discussion' => ['discussion', 'question', 'reddit', 'community', 'ama', 'opinion'],
            'pin' => ['pin', 'pinterest', 'save', 'infographic', 'seo'],
            'poll' => ['poll', 'vote', 'which', 'prefer', 'choose'],
            'longform_text' => ['deep', 'article', 'newsletter', 'long-form', 'longform'],
        ];

        if (isset($rules[$generator])) {
            foreach ($rules[$generator] as $keyword) {
                if (str_contains($topicLower, $keyword)) {
                    $score += 10;
                }
            }
        }

        if (str_contains($topicLower, 'linkedin') && str_contains($formatKey, 'linkedin')) {
            $score += 12;
        }
        if (str_contains($topicLower, 'tiktok') && str_contains($formatKey, 'tiktok')) {
            $score += 12;
        }
        if (str_contains($topicLower, 'instagram') && str_contains($formatKey, 'instagram')) {
            $score += 12;
        }
        if (str_contains($topicLower, 'youtube') && str_contains($formatKey, 'youtube')) {
            $score += 12;
        }

        foreach (explode(' ', $topicLower) as $word) {
            if ($word !== '' && str_contains($label, $word)) {
                $score += 3;
            }
        }

        return $score;
    }

    protected function mapPlatformForPromotion(string $platform): string
    {
        return match ($platform) {
            'twitter' => 'twitter',
            default => $platform,
        };
    }
}
