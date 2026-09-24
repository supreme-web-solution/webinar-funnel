<?php

namespace App\Services\Content;

final class PlatformFormatCatalog
{
    /** @var list<string> gpt-image-1 / OpenRouter supported output sizes */
    private const OPENROUTER_IMAGE_SIZES = ['1024x1024', '1024x1536', '1536x1024'];

    public const INTENSITY_STARTER = 'starter';

    public const INTENSITY_GROWTH = 'growth';

    public const INTENSITY_POWER = 'power';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allFormats(): array
    {
        return (array) config('content_formats.formats', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function platforms(): array
    {
        return (array) config('content_formats.platforms', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function intensityPresets(): array
    {
        return (array) config('content_formats.intensity_presets', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function format(string $key): ?array
    {
        $formats = $this->allFormats();

        return isset($formats[$key]) && is_array($formats[$key])
            ? array_merge(['key' => $key], $formats[$key])
            : null;
    }

    /**
     * @return list<string>
     */
    public function allFormatKeys(): array
    {
        return array_keys($this->allFormats());
    }

    /**
     * @return list<string>
     */
    public function formatsForPlatform(string $platform): array
    {
        return array_values(array_filter(
            array_keys($this->allFormats()),
            fn (string $key): bool => ($this->allFormats()[$key]['platform'] ?? '') === $platform,
        ));
    }

    /**
     * @return list<string>
     */
    public function defaultFormatsForIntensity(string $intensity): array
    {
        $presets = $this->intensityPresets();
        $preset = $presets[$intensity] ?? $presets[self::INTENSITY_GROWTH] ?? [];

        $formats = $preset['formats'] ?? 'all';
        if ($formats === 'all') {
            return $this->allFormatKeys();
        }

        return is_array($formats) ? array_values($formats) : $this->allFormatKeys();
    }

    /**
     * @param  list<string>|null  $enabledFormats
     * @return list<array<string, mixed>>
     */
    public function catalogPayload(?array $enabledFormats = null): array
    {
        $items = [];
        foreach ($this->allFormats() as $key => $spec) {
            if ($enabledFormats !== null && ! in_array($key, $enabledFormats, true)) {
                continue;
            }
            $platform = (string) ($spec['platform'] ?? '');
            $items[] = [
                'key' => $key,
                'platform' => $platform,
                'platform_label' => $this->platforms()[$platform]['label'] ?? ucfirst($platform),
                'label' => (string) ($spec['label'] ?? $key),
                'content_type' => (string) ($spec['content_type'] ?? 'text'),
                'generator' => (string) ($spec['generator'] ?? 'text'),
                'frequency_per_week' => (float) ($spec['frequency_per_week'] ?? 1),
                'hashtags_min' => $spec['hashtags_min'] ?? null,
                'hashtags_max' => $spec['hashtags_max'] ?? null,
                'duration_seconds' => $spec['duration_seconds'] ?? null,
                'aspect_ratio' => $spec['aspect_ratio'] ?? null,
                'size' => $spec['size'] ?? null,
                'signals' => $spec['signals'] ?? [],
            ];
        }

        return $items;
    }

    public function mapToPromotionContentType(string $formatKey): string
    {
        $spec = $this->format($formatKey);
        if ($spec === null) {
            return 'text';
        }

        $type = (string) ($spec['content_type'] ?? 'text');

        return match ($type) {
            'video' => 'video',
            'image' => 'image',
            default => 'text',
        };
    }

    /**
     * @param  array<string, mixed>|null  $spec
     */
    public function isVideoFormat(?array $spec): bool
    {
        if ($spec === null) {
            return false;
        }

        return (string) ($spec['content_type'] ?? '') === 'video';
    }

    public function promotionPlatformForFormat(string $formatKey): ?string
    {
        $spec = $this->format($formatKey);
        if ($spec === null) {
            return null;
        }

        $platform = (string) ($spec['platform'] ?? '');

        return $platform !== '' ? $platform : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function generationContextForFormat(string $formatKey): array
    {
        $spec = $this->format($formatKey);
        if ($spec === null) {
            return [];
        }

        return [
            'content_format' => $formatKey,
            'format_spec' => $spec,
            'generator' => $spec['generator'] ?? 'text',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $spec
     */
    public function generator(?array $spec): string
    {
        return is_array($spec) ? (string) ($spec['generator'] ?? 'text') : 'text';
    }

    /**
     * Multi-image swipe formats (carousel, photo album, idea pin, etc.).
     *
     * @param  array<string, mixed>|null  $spec
     */
    public function usesMultiSlideImages(?array $spec): bool
    {
        if ($spec === null) {
            return false;
        }

        return $this->generator($spec) === 'carousel'
            && (string) ($spec['content_type'] ?? '') === 'image';
    }

    /**
     * @param  array<string, mixed>|null  $spec
     */
    public function requiresImageAsset(?array $spec): bool
    {
        if ($spec === null) {
            return false;
        }

        $generator = $this->generator($spec);
        $contentType = (string) ($spec['content_type'] ?? 'text');

        return $contentType === 'image'
            && in_array($generator, ['carousel', 'image', 'pin', 'story'], true);
    }

    /**
     * OpenRouter / DALL-E compatible size string for image generation.
     *
     * @param  array<string, mixed>|null  $spec
     */
    public function openRouterImageSize(?array $spec): string
    {
        if ($spec === null) {
            return '1024x1024';
        }

        $size = (string) ($spec['size'] ?? '');
        if (preg_match('/^(\d+)x(\d+)$/', $size, $m)) {
            $w = (int) $m[1];
            $h = (int) $m[2];
            if ($w > 0 && $h > 0) {
                return $this->nearestOpenRouterSize($w, $h);
            }
        }

        return match ((string) ($spec['aspect_ratio'] ?? '')) {
            '9:16', '4:5', '2:3' => '1024x1536',
            '16:9' => '1536x1024',
            '1:1' => '1024x1024',
            default => '1024x1024',
        };
    }

    /**
     * Coerce any size string to a supported OpenRouter image size.
     */
    public function normalizeOpenRouterImageSize(string $size): string
    {
        if (in_array($size, self::OPENROUTER_IMAGE_SIZES, true)) {
            return $size;
        }

        if (preg_match('/^(\d+)x(\d+)$/', $size, $m)) {
            return $this->nearestOpenRouterSize((int) $m[1], (int) $m[2]);
        }

        return '1024x1024';
    }

    /**
     * @return array{aspect_ratio: string|null, target_size: string|null, openrouter_size: string, generator: string, duration_seconds: array<int, int>|null}
     */
    public function mediaSpec(?array $spec): array
    {
        $duration = null;
        if (is_array($spec['duration_seconds'] ?? null)) {
            $duration = array_map('intval', $spec['duration_seconds']);
        }

        return [
            'aspect_ratio' => is_array($spec) ? ($spec['aspect_ratio'] ?? null) : null,
            'target_size' => is_array($spec) ? ($spec['size'] ?? null) : null,
            'openrouter_size' => $this->openRouterImageSize($spec),
            'generator' => $this->generator($spec),
            'duration_seconds' => $duration,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $spec
     * @return array{min: int, max: int, render_max: int}
     */
    public function slideLimits(?array $spec): array
    {
        $min = max(2, (int) ($spec['slides_min'] ?? 3));
        $max = max($min, (int) ($spec['slides_max'] ?? 10));
        $cap = (int) config('promotion.carousel.max_slide_images', 6);

        return [
            'min' => $min,
            'max' => $max,
            'render_max' => min($max, max($min, $cap)),
        ];
    }

    /**
     * Platform-specific carousel image requirements for image model prompts.
     *
     * @param  array<string, mixed>|null  $spec
     */
    public function carouselImageFormatBlock(?array $spec): string
    {
        if ($spec === null) {
            return 'Portrait carousel slide for mobile feed.';
        }

        $platform = (string) ($spec['platform'] ?? 'social');
        $label = (string) ($spec['label'] ?? 'Carousel');
        $aspect = (string) ($spec['aspect_ratio'] ?? '4:5');
        $size = (string) ($spec['size'] ?? '');

        $sizeHint = $size !== '' ? " Export target {$size}." : '';

        $platformRules = match ($platform) {
            'instagram' => 'Instagram feed carousel: 4:5 portrait, text inside safe margins (not cropped on mobile), swipe-friendly, save-worthy.',
            'tiktok' => 'TikTok photo carousel: 9:16 vertical, bold mobile-readable type, thumb-stopping first frame.',
            'facebook' => 'Facebook carousel card: clear headline, readable at small size, album-style consistency.',
            'linkedin' => 'LinkedIn document carousel: professional, restrained palette, business-appropriate.',
            'pinterest' => 'Pinterest Idea Pin slide: vertical 2:3, SEO-friendly headline area, clean pin aesthetic.',
            default => 'Mobile-first carousel slide with readable typography.',
        };

        return "{$platformRules} Format: {$label}, aspect {$aspect}.{$sizeHint}";
    }

    /**
     * @param  array<string, mixed>|null  $spec
     */
    public function singleImageFormatBlock(?array $spec): string
    {
        if ($spec === null) {
            return '';
        }

        $block = $this->carouselImageFormatBlock($spec);

        return str_replace('carousel', 'feed post', $block);
    }

    /**
     * @param  array<string, mixed>|null  $spec
     */
    public function imagePromptHint(?array $spec): string
    {
        if ($spec === null) {
            return '';
        }

        $label = (string) ($spec['label'] ?? 'social post');
        $platform = (string) ($spec['platform'] ?? 'social media');
        $aspect = (string) ($spec['aspect_ratio'] ?? '');
        $size = (string) ($spec['size'] ?? '');

        return match ($this->generator($spec)) {
            'carousel' => $this->carouselImageFormatBlock($spec).' ',
            'story' => "Vertical story frame for {$platform} (9:16, full-screen mobile, text in upper/lower safe zones). ",
            'pin' => "Pinterest Pin visual for {$platform} ({$aspect}".($size !== '' ? ", {$size}" : '').', SEO headline area, save-worthy, uncluttered). ',
            'image' => $this->singleImageFormatBlock($spec).' ',
            default => "Optimized for {$label} on {$platform}. ",
        };
    }

    private function nearestOpenRouterSize(int $width, int $height): string
    {
        if ($width <= 0 || $height <= 0) {
            return '1024x1024';
        }

        $ratio = $width / $height;
        $best = '1024x1024';
        $bestDiff = PHP_FLOAT_MAX;

        foreach (self::OPENROUTER_IMAGE_SIZES as $size) {
            if (! preg_match('/^(\d+)x(\d+)$/', $size, $m)) {
                continue;
            }

            $sizeRatio = (int) $m[1] / (int) $m[2];
            $diff = abs($ratio - $sizeRatio);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $size;
            }
        }

        return $best;
    }
}
