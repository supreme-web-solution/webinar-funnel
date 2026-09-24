<?php

namespace App\Services\Promotion;

use App\Models\FunnelPromotionPost;
use App\Services\Cloudinary\CloudinaryService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders typography carousel slides with AI-selected layout templates.
 */
class CarouselTextSlideRenderer
{
    /** @var list<string> */
    public const LAYOUT_TYPES = [
        'left_editorial',
        'centered_hook',
        'accent_header',
        'card_inset',
        'bottom_stack',
        'side_stripe',
        'bold_statement',
        'dark_spotlight',
        'dark_editorial',
    ];

    /**
     * @return list<string>
     */
    public static function availableLayoutTypes(): array
    {
        return self::LAYOUT_TYPES;
    }

    /**
     * @return list<array{key: string, label: string, description: string, is_dark: bool}>
     */
    public static function layoutCatalog(): array
    {
        return [
            ['key' => 'left_editorial', 'label' => 'Left editorial', 'description' => 'Headline left, accent bar, lots of whitespace.', 'is_dark' => false],
            ['key' => 'centered_hook', 'label' => 'Centered hook', 'description' => 'Big centered statement for cover slides.', 'is_dark' => false],
            ['key' => 'accent_header', 'label' => 'Accent header', 'description' => 'Color band across the top, copy below.', 'is_dark' => false],
            ['key' => 'card_inset', 'label' => 'Card inset', 'description' => 'Content sits on a raised card over the canvas.', 'is_dark' => false],
            ['key' => 'bottom_stack', 'label' => 'Bottom stack', 'description' => 'Headline and body stacked along the bottom.', 'is_dark' => false],
            ['key' => 'side_stripe', 'label' => 'Side stripe', 'description' => 'Vertical stripe with numbered slide marker.', 'is_dark' => false],
            ['key' => 'bold_statement', 'label' => 'Bold statement', 'description' => 'Oversized type, one idea per slide.', 'is_dark' => false],
            ['key' => 'dark_spotlight', 'label' => 'Dark spotlight', 'description' => 'Dark canvas with a focused highlight.', 'is_dark' => true],
            ['key' => 'dark_editorial', 'label' => 'Dark editorial', 'description' => 'Magazine-style dark layout for bold topics.', 'is_dark' => true],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function lockedDesignForLayout(string $layoutType, string $topic = ''): array
    {
        $layout = in_array($layoutType, self::LAYOUT_TYPES, true) ? $layoutType : 'left_editorial';
        $isDark = in_array($layout, ['dark_spotlight', 'dark_editorial'], true);
        $entry = collect(self::layoutCatalog())->firstWhere('key', $layout);

        return [
            'background' => $isDark ? '#121214' : '#F7F5F0',
            'accent' => $isDark ? '#38BDBA' : '#0F766E',
            'text' => $isDark ? '#F5F5F4' : '#18181B',
            'headline_font' => 'Bold sans-serif',
            'body_font' => 'Clean sans-serif',
            'layout_type' => $layout,
            'layout' => is_array($entry) ? (string) ($entry['label'] ?? $layout) : $layout,
            'style' => is_array($entry)
                ? (string) ($entry['description'] ?? 'Typography-first carousel')
                : 'Typography-first carousel'.($topic !== '' ? ' for "'.$topic.'"' : ''),
            'source' => 'user_locked',
        ];
    }

    /**
     * @param  array<string, mixed>  $slide
     * @param  array<string, mixed>  $designSystem
     * @param  array<string, mixed>|null  $formatSpec
     * @return array{success: bool, url?: string, error?: string}
     */
    public function renderAndStore(
        FunnelPromotionPost $post,
        array $slide,
        int $slideIndex,
        int $totalSlides,
        ?array $formatSpec,
        array $designSystem,
    ): array {
        if (! extension_loaded('gd')) {
            return ['success' => false, 'error' => 'GD extension is required for carousel text slides.'];
        }

        try {
            $binary = $this->renderBinary($slide, $slideIndex, $totalSlides, $formatSpec, $designSystem);
            if ($binary === null) {
                return ['success' => false, 'error' => 'Could not render carousel text slide.'];
            }

            $url = $this->storeBinary($binary, $post->id);

            return $url !== null
                ? ['success' => true, 'url' => $url]
                : ['success' => false, 'error' => 'Could not store carousel text slide.'];
        } catch (\Throwable $e) {
            Log::error('[Promotion] CarouselTextSlideRenderer failed', [
                'post_id' => $post->id,
                'slide' => $slideIndex,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $slide
     * @param  array<string, mixed>  $designSystem
     * @param  array<string, mixed>|null  $formatSpec
     */
    public function renderBinary(
        array $slide,
        int $slideIndex,
        int $totalSlides,
        ?array $formatSpec,
        array $designSystem,
    ): ?string {
        [$width, $height] = $this->dimensions($formatSpec);

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            return null;
        }

        $layout = $this->resolveLayoutType($designSystem);
        $fonts = $this->resolveFontPair($layout);
        $palette = $this->buildPalette($image, $designSystem, $layout);
        imagefilledrectangle($image, 0, 0, $width, $height, $palette['background']);

        $slideLabel = 'SLIDE '.($slideIndex + 1).' / '.$totalSlides;
        $headline = $this->sanitizeText(trim((string) ($slide['headline'] ?? '')));
        $body = $this->sanitizeText(trim((string) ($slide['body'] ?? '')));

        $regularFont = $fonts['regular'];
        $boldFont = $fonts['bold'] ?? $regularFont;
        $displayFont = $fonts['display'] ?? $boldFont;

        if ($regularFont === null) {
            $this->renderFallbackStrings($image, $palette, $slideLabel, $headline, $body, $width, $height);
        } else {
            match ($layout) {
                'centered_hook' => $this->renderCenteredHook($image, $palette, $regularFont, $displayFont, $width, $height, $slideLabel, $headline, $body, $slideIndex),
                'accent_header' => $this->renderAccentHeader($image, $palette, $regularFont, $boldFont, $width, $height, $slideLabel, $headline, $body),
                'card_inset' => $this->renderCardInset($image, $palette, $regularFont, $displayFont, $width, $height, $slideLabel, $headline, $body),
                'bottom_stack' => $this->renderBottomStack($image, $palette, $regularFont, $boldFont, $width, $height, $slideLabel, $headline, $body),
                'side_stripe' => $this->renderSideStripe($image, $palette, $regularFont, $boldFont, $width, $height, $slideLabel, $headline, $body, $slideIndex + 1),
                'bold_statement' => $this->renderBoldStatement($image, $palette, $regularFont, $displayFont, $width, $height, $slideLabel, $headline, $body),
                'dark_spotlight' => $this->renderDarkSpotlight($image, $palette, $regularFont, $displayFont, $width, $height, $slideLabel, $headline, $body),
                'dark_editorial' => $this->renderDarkEditorial($image, $palette, $regularFont, $displayFont, $width, $height, $slideLabel, $headline, $body),
                default => $this->renderLeftEditorial($image, $palette, $regularFont, $displayFont, $width, $height, $slideLabel, $headline, $body),
            };
        }

        $this->drawSlideCounter($image, $palette, $regularFont, $width, $height, $slideIndex + 1, $totalSlides, $layout);

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        return is_string($binary) && $binary !== '' ? $binary : null;
    }

    /**
     * @param  array<string, mixed>  $designSystem
     */
    public function resolveLayoutType(array $designSystem): string
    {
        $explicit = strtolower(trim((string) ($designSystem['layout_type'] ?? '')));
        if (in_array($explicit, self::LAYOUT_TYPES, true)) {
            return $explicit;
        }

        $raw = strtolower((string) ($designSystem['layout'] ?? $explicit));

        return match (true) {
            str_contains($raw, 'dark_spotlight') || str_contains($raw, 'spotlight') => 'dark_spotlight',
            str_contains($raw, 'dark_editorial') || str_contains($raw, 'dark editorial') => 'dark_editorial',
            str_contains($raw, 'bottom') || str_contains($raw, 'stack') => 'bottom_stack',
            str_contains($raw, 'stripe') || str_contains($raw, 'side_stripe') => 'side_stripe',
            str_contains($raw, 'bold') || str_contains($raw, 'statement') => 'bold_statement',
            str_contains($raw, 'center') || str_contains($raw, 'centered') => 'centered_hook',
            str_contains($raw, 'band') || str_contains($raw, 'header') || str_contains($raw, 'accent_header') => 'accent_header',
            str_contains($raw, 'card') || str_contains($raw, 'inset') => 'card_inset',
            default => 'left_editorial',
        };
    }

    /**
     * @param  \GdImage  $image
     * @param  array<string, mixed>  $designSystem
     * @return array{background: int, accent: int, text: int, muted: int, onAccent: int, bgRgb: array{int,int,int}, accentRgb: array{int,int,int}, isDark: bool}
     */
    private function buildPalette($image, array $designSystem, string $layout): array
    {
        $isDarkLayout = in_array($layout, ['dark_spotlight', 'dark_editorial'], true);

        $bgRgb = $this->parseColor((string) ($designSystem['background'] ?? ''), $isDarkLayout ? [18, 18, 20] : [252, 251, 248]);
        $accentRgb = $this->parseColor((string) ($designSystem['accent'] ?? ''), $isDarkLayout ? [56, 189, 186] : [15, 118, 110]);
        $textRgb = $this->parseColor((string) ($designSystem['text'] ?? ''), $isDarkLayout ? [245, 245, 244] : [24, 24, 27]);

        $isDark = $isDarkLayout || $this->isDarkRgb($bgRgb);

        if ($isDark && $this->luminance($textRgb) < 0.55) {
            $textRgb = [245, 245, 244];
        }

        if (! $isDark && $this->luminance($textRgb) > 0.65) {
            $textRgb = [24, 24, 27];
        }

        if ($isDarkLayout && ! $this->isDarkRgb($bgRgb)) {
            $bgRgb = [18, 18, 20];
        }

        $mutedRgb = $isDark
            ? [
                (int) min(255, $textRgb[0] * 0.72 + $bgRgb[0] * 0.28),
                (int) min(255, $textRgb[1] * 0.72 + $bgRgb[1] * 0.28),
                (int) min(255, $textRgb[2] * 0.72 + $bgRgb[2] * 0.28),
            ]
            : [
                (int) ($textRgb[0] * 0.55 + $bgRgb[0] * 0.45),
                (int) ($textRgb[1] * 0.55 + $bgRgb[1] * 0.45),
                (int) ($textRgb[2] * 0.55 + $bgRgb[2] * 0.45),
            ];

        return [
            'background' => imagecolorallocate($image, ...$bgRgb),
            'accent' => imagecolorallocate($image, ...$accentRgb),
            'text' => imagecolorallocate($image, ...$textRgb),
            'muted' => imagecolorallocate($image, ...$mutedRgb),
            'onAccent' => $this->luminance($accentRgb) < 0.55
                ? imagecolorallocate($image, 255, 255, 255)
                : imagecolorallocate($image, ...$bgRgb),
            'bgRgb' => $bgRgb,
            'accentRgb' => $accentRgb,
            'isDark' => $isDark,
        ];
    }

    /**
     * @return array{regular: ?string, bold: ?string, display: ?string}
     */
    private function resolveFontPair(string $layout): array
    {
        $bundled = resource_path('fonts');

        return match ($layout) {
            'left_editorial', 'dark_editorial' => [
                'regular' => $this->firstReadable([
                    $bundled.'/Inter-Regular.ttf',
                    'C:\\Windows\\Fonts\\calibri.ttf',
                    'C:\\Windows\\Fonts\\segoeui.ttf',
                    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                ]),
                'bold' => $this->firstReadable([
                    $bundled.'/Inter-SemiBold.ttf',
                    'C:\\Windows\\Fonts\\calibrib.ttf',
                    'C:\\Windows\\Fonts\\segoeuib.ttf',
                    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                ]),
                'display' => $this->firstReadable([
                    $bundled.'/PlayfairDisplay-Bold.ttf',
                    'C:\\Windows\\Fonts\\georgiab.ttf',
                    'C:\\Windows\\Fonts\\cambriab.ttf',
                    'C:\\Windows\\Fonts\\timesbd.ttf',
                    'C:\\Windows\\Fonts\\calibrib.ttf',
                ]),
            ],
            'centered_hook', 'dark_spotlight' => [
                'regular' => $this->firstReadable([
                    $bundled.'/Inter-Regular.ttf',
                    'C:\\Windows\\Fonts\\segoeui.ttf',
                    'C:\\Windows\\Fonts\\calibri.ttf',
                    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                ]),
                'bold' => $this->firstReadable([
                    $bundled.'/Inter-Bold.ttf',
                    'C:\\Windows\\Fonts\\segoeuib.ttf',
                    'C:\\Windows\\Fonts\\calibrib.ttf',
                    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                ]),
                'display' => $this->firstReadable([
                    $bundled.'/Inter-Bold.ttf',
                    'C:\\Windows\\Fonts\\segoeuib.ttf',
                    'C:\\Windows\\Fonts\\calibrib.ttf',
                ]),
            ],
            'bold_statement', 'bottom_stack' => [
                'regular' => $this->firstReadable([
                    $bundled.'/Inter-Regular.ttf',
                    'C:\\Windows\\Fonts\\calibri.ttf',
                    'C:\\Windows\\Fonts\\segoeui.ttf',
                ]),
                'bold' => $this->firstReadable([
                    'C:\\Windows\\Fonts\\framd.ttf',
                    'C:\\Windows\\Fonts\\arialbd.ttf',
                    $bundled.'/Inter-Bold.ttf',
                    'C:\\Windows\\Fonts\\segoeuib.ttf',
                ]),
                'display' => $this->firstReadable([
                    'C:\\Windows\\Fonts\\framd.ttf',
                    'C:\\Windows\\Fonts\\impact.ttf',
                    $bundled.'/Inter-Bold.ttf',
                    'C:\\Windows\\Fonts\\arialbd.ttf',
                ]),
            ],
            default => [
                'regular' => $this->firstReadable([
                    $bundled.'/Inter-Regular.ttf',
                    'C:\\Windows\\Fonts\\calibri.ttf',
                    'C:\\Windows\\Fonts\\segoeui.ttf',
                    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                ]),
                'bold' => $this->firstReadable([
                    $bundled.'/Inter-SemiBold.ttf',
                    'C:\\Windows\\Fonts\\calibrib.ttf',
                    'C:\\Windows\\Fonts\\segoeuib.ttf',
                    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                ]),
                'display' => $this->firstReadable([
                    $bundled.'/Inter-Bold.ttf',
                    'C:\\Windows\\Fonts\\calibrib.ttf',
                    'C:\\Windows\\Fonts\\segoeuib.ttf',
                ]),
            ],
        };
    }

    /**
     * @param  list<string>  $paths
     */
    private function firstReadable(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  \GdImage  $image
     * @param  array{background: int, accent: int, text: int, muted: int}  $palette
     */
    private function renderLeftEditorial(
        $image,
        array $palette,
        string $regularFont,
        ?string $displayFont,
        int $width,
        int $height,
        string $slideLabel,
        string $headline,
        string $body,
    ): void {
        $paddingX = (int) round($width * 0.1);
        $paddingY = (int) round($height * 0.11);
        $contentWidth = $width - ($paddingX * 2) - 32;
        $textX = $paddingX + 32;

        $labelSize = max(14, (int) round($width * 0.02));
        $headlineSize = $this->fitHeadlineSize($headline, $displayFont ?? $regularFont, max(34, (int) round($width * 0.048)), $contentWidth, 4);
        $bodySize = max(20, (int) round($width * 0.027));

        $y = $paddingY;
        $y = $this->drawSingleLine($image, strtoupper($slideLabel), $regularFont, $labelSize, $textX, $y, $palette['accent']);
        $this->drawHorizontalRule($image, $textX, $y + 8, $textX + (int) ($contentWidth * 0.35), $y + 8, $palette['accent']);
        $y += (int) round($labelSize * 2.2);

        $headlineFont = $displayFont ?? $regularFont;
        if ($headline !== '') {
            $y = $this->drawWrappedBlock($image, $this->wrapText($headline, $headlineFont, $headlineSize, $contentWidth), $headlineFont, $headlineSize, $textX, $y, $palette['text'], 1.45);
            $y += (int) round($headlineSize * 0.35);
        }

        if ($body !== '') {
            $this->drawWrappedBlock($image, $this->wrapText($body, $regularFont, $bodySize, $contentWidth), $regularFont, $bodySize, $textX, $y, $palette['muted'], 1.5);
        }

        imagefilledrectangle($image, $paddingX, $paddingY - 4, $paddingX + 6, $height - $paddingY, $palette['accent']);
    }

    /**
     * @param  \GdImage  $image
     * @param  array{background: int, accent: int, text: int, muted: int}  $palette
     */
    private function renderCenteredHook(
        $image,
        array $palette,
        string $regularFont,
        ?string $displayFont,
        int $width,
        int $height,
        string $slideLabel,
        string $headline,
        string $body,
        int $slideIndex,
    ): void {
        $contentWidth = (int) round($width * 0.72);
        $labelSize = max(13, (int) round($width * 0.019));
        $headlineFont = $displayFont ?? $regularFont;
        $headlineSize = $this->fitHeadlineSize($headline, $headlineFont, max(36, (int) round($width * 0.052)), $contentWidth, 4);
        $bodySize = max(20, (int) round($width * 0.026));

        $headlineLines = $headline !== '' ? $this->wrapText($headline, $headlineFont, $headlineSize, $contentWidth) : [];
        $bodyLines = $body !== '' ? array_slice($this->wrapText($body, $regularFont, $bodySize, $contentWidth), 0, 4) : [];

        $blockHeight = $this->blockHeight($regularFont, $labelSize, 1)
            + $this->blockHeight($headlineFont, $headlineSize, count($headlineLines), 1.45)
            + $this->blockHeight($regularFont, $bodySize, count($bodyLines), 1.5)
            + (int) round($height * 0.08);
        $startY = max((int) round($height * 0.12), (int) (($height - $blockHeight) / 2));

        $labelY = $startY + $this->lineAscent($regularFont, $labelSize);
        $this->drawCenteredText($image, strtoupper($slideLabel), $regularFont, $labelSize, $labelY, $width, $palette['accent']);
        $this->drawHorizontalRule($image, (int) ($width * 0.32), $labelY + 14, (int) ($width * 0.44), $labelY + 14, $palette['accent']);
        $this->drawHorizontalRule($image, (int) ($width * 0.56), $labelY + 14, (int) ($width * 0.68), $labelY + 14, $palette['accent']);

        $y = $labelY + (int) round($labelSize * 2.8);
        if ($headline !== '') {
            $y = $this->drawCenteredBlock($image, $headlineLines, $headlineFont, $headlineSize, $y, $width, $palette['text'], 1.45);
            $y += (int) round($headlineSize * 0.4);
        }

        $this->drawCenteredBlock($image, $bodyLines, $regularFont, $bodySize, $y, $width, $palette['muted'], 1.5);

        if ($slideIndex === 0) {
            imagefilledellipse($image, (int) ($width / 2), (int) round($height * 0.07), 10, 10, $palette['accent']);
        }
    }

    /**
     * @param  \GdImage  $image
     * @param  array{background: int, accent: int, text: int, muted: int, onAccent: int}  $palette
     */
    private function renderAccentHeader(
        $image,
        array $palette,
        string $regularFont,
        ?string $boldFont,
        int $width,
        int $height,
        string $slideLabel,
        string $headline,
        string $body,
    ): void {
        $headerH = (int) round($height * 0.15);
        imagefilledrectangle($image, 0, 0, $width, $headerH, $palette['accent']);

        $labelSize = max(14, (int) round($width * 0.02));
        imagettftext($image, $labelSize, 0, (int) round($width * 0.08), (int) ($headerH * 0.62), $palette['onAccent'], $regularFont, strtoupper($slideLabel));

        $paddingX = (int) round($width * 0.1);
        $contentWidth = $width - ($paddingX * 2);
        $headlineSize = $this->fitHeadlineSize($headline, $boldFont ?? $regularFont, max(32, (int) round($width * 0.046)), $contentWidth, 4);
        $bodySize = max(20, (int) round($width * 0.026));
        $y = $headerH + (int) round($height * 0.05);

        if ($headline !== '' && $boldFont !== null) {
            $y = $this->drawWrappedBlock($image, $this->wrapText($headline, $boldFont, $headlineSize, $contentWidth), $boldFont, $headlineSize, $paddingX, $y, $palette['text'], 1.45);
            $y += (int) round($headlineSize * 0.35);
        }

        if ($body !== '') {
            $this->drawWrappedBlock($image, $this->wrapText($body, $regularFont, $bodySize, $contentWidth), $regularFont, $bodySize, $paddingX, $y, $palette['muted'], 1.5);
        }
    }

    /**
     * @param  \GdImage  $image
     * @param  array{background: int, accent: int, text: int, muted: int, bgRgb: array{int,int,int}}  $palette
     */
    private function renderCardInset(
        $image,
        array $palette,
        string $regularFont,
        ?string $displayFont,
        int $width,
        int $height,
        string $slideLabel,
        string $headline,
        string $body,
    ): void {
        $cardX = (int) round($width * 0.07);
        $cardY = (int) round($height * 0.09);
        $cardW = (int) round($width * 0.86);
        $cardH = (int) round($height * 0.74);
        $cardBg = imagecolorallocate(
            $image,
            min(255, $palette['bgRgb'][0] + ($palette['isDark'] ? 14 : 10)),
            min(255, $palette['bgRgb'][1] + ($palette['isDark'] ? 14 : 10)),
            min(255, $palette['bgRgb'][2] + ($palette['isDark'] ? 14 : 10)),
        );
        $shadow = imagecolorallocate($image, max(0, $palette['bgRgb'][0] - 18), max(0, $palette['bgRgb'][1] - 18), max(0, $palette['bgRgb'][2] - 18));
        imagefilledrectangle($image, $cardX + 6, $cardY + 6, $cardX + $cardW + 6, $cardY + $cardH + 6, $shadow);
        imagefilledrectangle($image, $cardX, $cardY, $cardX + $cardW, $cardY + $cardH, $cardBg);
        imagerectangle($image, $cardX, $cardY, $cardX + $cardW, $cardY + $cardH, $palette['accent']);
        imagerectangle($image, $cardX + 2, $cardY + 2, $cardX + $cardW - 2, $cardY + $cardH - 2, $palette['accent']);

        $paddingX = $cardX + (int) round($width * 0.08);
        $contentWidth = $cardW - (int) round($width * 0.16);
        $labelSize = max(13, (int) round($width * 0.019));
        $headlineFont = $displayFont ?? $regularFont;
        $headlineSize = $this->fitHeadlineSize($headline, $headlineFont, max(30, (int) round($width * 0.042)), $contentWidth, 4);
        $bodySize = max(19, (int) round($width * 0.025));

        $y = $cardY + (int) round($height * 0.05);
        $y = $this->drawSingleLine($image, strtoupper($slideLabel), $regularFont, $labelSize, $paddingX, $y, $palette['accent']);
        $y += (int) round($labelSize * 2.4);

        if ($headline !== '') {
            $y = $this->drawWrappedBlock($image, $this->wrapText($headline, $headlineFont, $headlineSize, $contentWidth), $headlineFont, $headlineSize, $paddingX, $y, $palette['text'], 1.45);
            $y += (int) round($headlineSize * 0.35);
        }

        if ($body !== '') {
            $this->drawWrappedBlock($image, $this->wrapText($body, $regularFont, $bodySize, $contentWidth), $regularFont, $bodySize, $paddingX, $y, $palette['muted'], 1.5);
        }
    }

    /**
     * @param  \GdImage  $image
     * @param  array{background: int, accent: int, text: int, muted: int}  $palette
     */
    private function renderBottomStack(
        $image,
        array $palette,
        string $regularFont,
        ?string $boldFont,
        int $width,
        int $height,
        string $slideLabel,
        string $headline,
        string $body,
    ): void {
        $paddingX = (int) round($width * 0.1);
        $contentWidth = $width - ($paddingX * 2);

        for ($i = 0; $i < 3; $i++) {
            imagefilledellipse($image, $paddingX + 12 + ($i * 22), (int) round($height * 0.14), 8, 8, $palette['accent']);
        }
        imagefilledrectangle($image, $paddingX, (int) round($height * 0.2), $paddingX + (int) ($width * 0.22), (int) round($height * 0.205), $palette['accent']);

        $labelSize = max(13, (int) round($width * 0.019));
        $headlineSize = $this->fitHeadlineSize($headline, $boldFont ?? $regularFont, max(34, (int) round($width * 0.048)), $contentWidth, 3);
        $bodySize = max(19, (int) round($width * 0.025));
        $y = (int) round($height * 0.5);

        $y = $this->drawSingleLine($image, strtoupper($slideLabel), $regularFont, $labelSize, $paddingX, $y, $palette['accent']);
        $y += (int) round($labelSize * 2.4);

        if ($headline !== '' && $boldFont !== null) {
            $y = $this->drawWrappedBlock($image, $this->wrapText($headline, $boldFont, $headlineSize, $contentWidth), $boldFont, $headlineSize, $paddingX, $y, $palette['text'], 1.45);
            $y += (int) round($headlineSize * 0.35);
        }

        if ($body !== '') {
            $this->drawWrappedBlock($image, $this->wrapText($body, $regularFont, $bodySize, $contentWidth), $regularFont, $bodySize, $paddingX, $y, $palette['muted'], 1.5);
        }
    }

    /**
     * @param  \GdImage  $image
     * @param  array{background: int, accent: int, text: int, muted: int}  $palette
     */
    private function renderSideStripe(
        $image,
        array $palette,
        string $regularFont,
        ?string $boldFont,
        int $width,
        int $height,
        string $slideLabel,
        string $headline,
        string $body,
        int $slideNumber,
    ): void {
        $stripeW = (int) round($width * 0.09);
        imagefilledrectangle($image, 0, 0, $stripeW, $height, $palette['accent']);

        $numSize = max(28, (int) round($width * 0.04));
        $this->drawCenteredText($image, (string) $slideNumber, $boldFont ?? $regularFont, $numSize, (int) ($stripeW / 2) + (int) ($numSize * 0.35), $stripeW + 20, $palette['onAccent']);

        $paddingX = $stripeW + (int) round($width * 0.08);
        $contentWidth = $width - $paddingX - (int) round($width * 0.08);
        $paddingY = (int) round($height * 0.11);
        $labelSize = max(13, (int) round($width * 0.019));
        $headlineSize = $this->fitHeadlineSize($headline, $boldFont ?? $regularFont, max(32, (int) round($width * 0.044)), $contentWidth, 4);
        $bodySize = max(19, (int) round($width * 0.025));

        $y = $paddingY;
        $y = $this->drawSingleLine($image, strtoupper($slideLabel), $regularFont, $labelSize, $paddingX, $y, $palette['accent']);
        $y += (int) round($labelSize * 2.4);

        if ($headline !== '' && $boldFont !== null) {
            $y = $this->drawWrappedBlock($image, $this->wrapText($headline, $boldFont, $headlineSize, $contentWidth), $boldFont, $headlineSize, $paddingX, $y, $palette['text'], 1.45);
            $y += (int) round($headlineSize * 0.35);
        }

        if ($body !== '') {
            $this->drawWrappedBlock($image, $this->wrapText($body, $regularFont, $bodySize, $contentWidth), $regularFont, $bodySize, $paddingX, $y, $palette['muted'], 1.5);
        }
    }

    /**
     * @param  \GdImage  $image
     * @param  array{background: int, accent: int, text: int, muted: int}  $palette
     */
    private function renderBoldStatement(
        $image,
        array $palette,
        string $regularFont,
        ?string $displayFont,
        int $width,
        int $height,
        string $slideLabel,
        string $headline,
        string $body,
    ): void {
        $paddingX = (int) round($width * 0.1);
        $contentWidth = $width - ($paddingX * 2);
        $labelSize = max(12, (int) round($width * 0.017));
        $headlineFont = $displayFont ?? $regularFont;
        $headlineSize = $this->fitHeadlineSize($headline, $headlineFont, max(40, (int) round($width * 0.056)), $contentWidth, 3);
        $bodySize = max(19, (int) round($width * 0.025));

        $this->drawSingleLine($image, strtoupper($slideLabel), $regularFont, $labelSize, $paddingX, (int) round($height * 0.1), $palette['accent']);

        $y = (int) round($height * 0.2);
        $headlineEndY = $y;
        if ($headline !== '') {
            $headlineEndY = $this->drawWrappedBlock($image, $this->wrapText($headline, $headlineFont, $headlineSize, $contentWidth), $headlineFont, $headlineSize, $paddingX, $y, $palette['text'], 1.4);
            $this->drawHorizontalRule($image, $paddingX, $headlineEndY + 12, $width - $paddingX, $headlineEndY + 16, $palette['accent']);
            $y = $headlineEndY + (int) round($headlineSize * 0.55);
        }

        if ($body !== '') {
            $this->drawWrappedBlock($image, array_slice($this->wrapText($body, $regularFont, $bodySize, $contentWidth), 0, 3), $regularFont, $bodySize, $paddingX, $y, $palette['muted'], 1.5);
        }
    }

    /**
     * @param  \GdImage  $image
     * @param  array{background: int, accent: int, text: int, muted: int, accentRgb: array{int,int,int}}  $palette
     */
    private function renderDarkSpotlight(
        $image,
        array $palette,
        string $regularFont,
        ?string $displayFont,
        int $width,
        int $height,
        string $slideLabel,
        string $headline,
        string $body,
    ): void {
        $glow = imagecolorallocatealpha(
            $image,
            $palette['accentRgb'][0],
            $palette['accentRgb'][1],
            $palette['accentRgb'][2],
            90,
        );
        imagefilledellipse($image, (int) ($width / 2), (int) round($height * 0.42), (int) round($width * 0.72), (int) round($height * 0.38), $glow);

        $contentWidth = (int) round($width * 0.74);
        $labelSize = max(13, (int) round($width * 0.019));
        $headlineFont = $displayFont ?? $regularFont;
        $headlineSize = $this->fitHeadlineSize($headline, $headlineFont, max(34, (int) round($width * 0.05)), $contentWidth, 4);
        $bodySize = max(19, (int) round($width * 0.026));

        $headlineLines = $headline !== '' ? $this->wrapText($headline, $headlineFont, $headlineSize, $contentWidth) : [];
        $bodyLines = $body !== '' ? array_slice($this->wrapText($body, $regularFont, $bodySize, $contentWidth), 0, 4) : [];
        $blockHeight = $this->blockHeight($regularFont, $labelSize, 1)
            + $this->blockHeight($headlineFont, $headlineSize, count($headlineLines), 1.45)
            + $this->blockHeight($regularFont, $bodySize, count($bodyLines), 1.5)
            + 40;
        $startY = max((int) round($height * 0.14), (int) (($height - $blockHeight) / 2));

        $labelY = $startY + $this->lineAscent($regularFont, $labelSize);
        $this->drawCenteredText($image, strtoupper($slideLabel), $regularFont, $labelSize, $labelY, $width, $palette['accent']);

        $y = $labelY + (int) round($labelSize * 2.6);
        if ($headline !== '') {
            $y = $this->drawCenteredBlock($image, $headlineLines, $headlineFont, $headlineSize, $y, $width, $palette['text'], 1.45);
            $y += (int) round($headlineSize * 0.4);
        }

        $this->drawCenteredBlock($image, $bodyLines, $regularFont, $bodySize, $y, $width, $palette['muted'], 1.5);
    }

    /**
     * @param  \GdImage  $image
     * @param  array{background: int, accent: int, text: int, muted: int}  $palette
     */
    private function renderDarkEditorial(
        $image,
        array $palette,
        string $regularFont,
        ?string $displayFont,
        int $width,
        int $height,
        string $slideLabel,
        string $headline,
        string $body,
    ): void {
        $paddingX = (int) round($width * 0.11);
        $paddingY = (int) round($height * 0.12);
        $contentWidth = $width - ($paddingX * 2);

        $labelSize = max(13, (int) round($width * 0.019));
        $headlineFont = $displayFont ?? $regularFont;
        $headlineSize = $this->fitHeadlineSize($headline, $headlineFont, max(34, (int) round($width * 0.048)), $contentWidth, 4);
        $bodySize = max(19, (int) round($width * 0.026));

        $y = $paddingY;
        $y = $this->drawSingleLine($image, strtoupper($slideLabel), $regularFont, $labelSize, $paddingX, $y, $palette['accent']);

        $y += (int) round($labelSize * 2.2);
        if ($headline !== '') {
            $y = $this->drawWrappedBlock($image, $this->wrapText($headline, $headlineFont, $headlineSize, $contentWidth), $headlineFont, $headlineSize, $paddingX, $y, $palette['text'], 1.45);
            $this->drawHorizontalRule($image, $paddingX, $y + 10, $paddingX + (int) ($contentWidth * 0.5), $y + 13, $palette['accent']);
            $y += (int) round($headlineSize * 0.5);
        }

        if ($body !== '') {
            $this->drawWrappedBlock($image, $this->wrapText($body, $regularFont, $bodySize, $contentWidth), $regularFont, $bodySize, $paddingX, $y, $palette['muted'], 1.5);
        }

        imagefilledrectangle($image, $paddingX - 20, $paddingY, $paddingX - 14, $height - $paddingY, $palette['accent']);
    }

    /**
     * @param  \GdImage  $image
     * @param  array{background: int, accent: int, text: int, muted: int}  $palette
     */
    private function drawSlideCounter($image, array $palette, ?string $font, int $width, int $height, int $current, int $total, string $layout): void
    {
        if ($font === null || in_array($layout, ['accent_header', 'side_stripe'], true)) {
            return;
        }

        $size = max(13, (int) round($width * 0.017));
        $label = "{$current}/{$total}";
        $box = imagettfbbox($size, 0, $font, $label);
        $textW = abs($box[2] - $box[0]);
        $x = $width - (int) round($width * 0.08) - $textW;
        $y = $height - (int) round($height * 0.06);
        imagettftext($image, $size, 0, $x, $y, $palette['muted'], $font, $label);
    }

    /**
     * @param  \GdImage  $image
     */
    private function drawCenteredText($image, string $text, string $font, float $size, int $y, int $width, int $color): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $textW = abs($box[2] - $box[0]);
        $x = (int) (($width - $textW) / 2);
        imagettftext($image, $size, 0, max(0, $x), $y, $color, $font, $text);
    }

    /**
     * @param  \GdImage  $image
     * @param  list<string>  $lines
     */
    private function drawWrappedBlock($image, array $lines, string $font, float $size, int $x, int $y, int $color, float $lineMultiplier): int
    {
        if ($lines === []) {
            return $y;
        }

        $y += $this->lineAscent($font, $size);
        $advance = $this->lineAdvance($font, $size, $lineMultiplier);

        foreach ($lines as $line) {
            imagettftext($image, $size, 0, $x, $y, $color, $font, $line);
            $y += $advance;
        }

        return $y - (int) round($advance * 0.35);
    }

    /**
     * @param  \GdImage  $image
     * @param  list<string>  $lines
     */
    private function drawCenteredBlock($image, array $lines, string $font, float $size, int $y, int $width, int $color, float $lineMultiplier): int
    {
        if ($lines === []) {
            return $y;
        }

        $y += $this->lineAscent($font, $size);
        $advance = $this->lineAdvance($font, $size, $lineMultiplier);

        foreach ($lines as $line) {
            $this->drawCenteredText($image, $line, $font, $size, $y, $width, $color);
            $y += $advance;
        }

        return $y - (int) round($advance * 0.35);
    }

    /**
     * @param  \GdImage  $image
     */
    private function drawSingleLine($image, string $text, string $font, float $size, int $x, int $y, int $color): int
    {
        $y += $this->lineAscent($font, $size);
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);

        return $y;
    }

    /**
     * @param  \GdImage  $image
     */
    private function drawHorizontalRule($image, int $x1, int $y1, int $x2, int $y2, int $color): void
    {
        imageline($image, $x1, $y1, $x2, $y2, $color);
    }

    /**
     * @param  \GdImage  $image
     * @param  array{background: int, accent: int, text: int, muted: int}  $palette
     */
    private function renderFallbackStrings($image, array $palette, string $slideLabel, string $headline, string $body, int $width, int $height): void
    {
        $x = (int) round($width * 0.1);
        imagestring($image, 3, $x, (int) round($height * 0.1), $slideLabel, $palette['accent']);
        if ($headline !== '') {
            imagestring($image, 5, $x, (int) round($height * 0.18), mb_substr($headline, 0, 42), $palette['text']);
        }
        if ($body !== '') {
            imagestring($image, 4, $x, (int) round($height * 0.28), mb_substr($body, 0, 80), $palette['muted']);
        }
    }

    public function sanitizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  array<string, mixed>|null  $formatSpec
     * @return array{0: int, 1: int}
     */
    private function dimensions(?array $formatSpec): array
    {
        $size = is_array($formatSpec) ? (string) ($formatSpec['size'] ?? '') : '';
        if (preg_match('/^(\d+)x(\d+)$/', $size, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return match (is_array($formatSpec) ? (string) ($formatSpec['aspect_ratio'] ?? '4:5') : '4:5') {
            '1:1' => [1080, 1080],
            '9:16' => [1080, 1920],
            '16:9' => [1920, 1080],
            '2:3' => [1000, 1500],
            default => [1080, 1350],
        };
    }

    /**
     * @param  array{int, int, int}  $fallback
     * @return array{int, int, int}
     */
    public function parseColor(string $value, array $fallback): array
    {
        if (preg_match('/#([0-9a-fA-F]{6})/', $value, $m)) {
            $hex = $m[1];

            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        }

        return $fallback;
    }

    /**
     * @return list<string>
     */
    private function wrapText(string $text, string $fontPath, float $fontSize, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $candidate = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($fontSize, 0, $fontPath, $candidate);
            $width = abs($box[2] - $box[0]);

            if ($width > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [] : $lines;
    }

    private function fitHeadlineSize(string $headline, ?string $font, float $baseSize, int $maxWidth, int $maxLines): float
    {
        if ($headline === '' || $font === null) {
            return $baseSize;
        }

        $size = $baseSize;
        for ($i = 0; $i < 6; $i++) {
            if (count($this->wrapText($headline, $font, $size, $maxWidth)) <= $maxLines) {
                return $size;
            }
            $size *= 0.9;
        }

        return max(24, $size);
    }

    private function lineAscent(string $font, float $size): int
    {
        $box = imagettfbbox($size, 0, $font, 'Ay');
        if ($box === false) {
            return (int) round($size * 0.8);
        }

        return (int) abs($box[7]);
    }

    private function lineAdvance(string $font, float $size, float $multiplier = 1.4): int
    {
        $box = imagettfbbox($size, 0, $font, 'Ay');
        if ($box === false) {
            return (int) round($size * $multiplier);
        }

        $height = abs($box[7] - $box[1]);

        return (int) round(max($height * $multiplier, $size * 1.2));
    }

    private function blockHeight(?string $font, float $size, int $lineCount, float $multiplier = 1.4): int
    {
        if ($font === null || $lineCount <= 0) {
            return 0;
        }

        return $this->lineAscent($font, $size) + ($lineCount * $this->lineAdvance($font, $size, $multiplier));
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private function luminance(array $rgb): float
    {
        return (0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2]) / 255;
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private function isDarkRgb(array $rgb): bool
    {
        return $this->luminance($rgb) < 0.42;
    }

    private function storeBinary(string $binary, int $postId): ?string
    {
        $folder = 'promotion-assets/'.date('Y/m');
        $publicId = Str::uuid()->toString();

        $cloudinary = app(CloudinaryService::class);
        if ($cloudinary->isConfigured()) {
            $url = $cloudinary->uploadBinary($binary, $folder, $publicId);
            if ($url) {
                return $url;
            }
        }

        $path = "{$folder}/{$publicId}.png";
        Storage::disk('public')->put($path, $binary);

        return Storage::disk('public')->url($path);
    }
}
