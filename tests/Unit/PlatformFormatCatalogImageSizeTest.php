<?php

namespace Tests\Unit;

use App\Services\Content\PlatformFormatCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlatformFormatCatalogImageSizeTest extends TestCase
{
    private const ALLOWED = ['1024x1024', '1024x1536', '1536x1024'];

    #[DataProvider('targetSizeProvider')]
    public function test_nearest_openrouter_size_maps_to_supported_values(int $w, int $h, string $expected): void
    {
        $catalog = new PlatformFormatCatalog;

        $method = new \ReflectionMethod($catalog, 'nearestOpenRouterSize');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($catalog, $w, $h));
        $this->assertContains($method->invoke($catalog, $w, $h), self::ALLOWED);
    }

    public static function targetSizeProvider(): array
    {
        return [
            'instagram carousel 4:5' => [1080, 1350, '1024x1536'],
            'instagram square' => [1080, 1080, '1024x1024'],
            'story 9:16' => [1080, 1920, '1024x1536'],
            'landscape 16:9' => [1920, 1080, '1536x1024'],
            'pinterest 2:3' => [1000, 1500, '1024x1536'],
        ];
    }

    public function test_all_image_formats_resolve_to_supported_openrouter_sizes(): void
    {
        $catalog = new PlatformFormatCatalog;

        foreach ($catalog->allFormats() as $key => $spec) {
            if (($spec['content_type'] ?? '') !== 'image') {
                continue;
            }

            $size = $catalog->openRouterImageSize(array_merge(['key' => $key], $spec));

            $this->assertContains(
                $size,
                self::ALLOWED,
                "Format {$key} resolved to unsupported size {$size}",
            );
        }
    }

    public function test_normalize_rejects_invalid_size_strings(): void
    {
        $catalog = new PlatformFormatCatalog;

        $this->assertSame('1024x1536', $catalog->normalizeOpenRouterImageSize('1024x1280'));
        $this->assertSame('1024x1024', $catalog->normalizeOpenRouterImageSize('1024x1024'));
    }
}
