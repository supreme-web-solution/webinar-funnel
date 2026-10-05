<?php

namespace App\Services\Campaigns;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class LeadMagnetPdfService
{
    /**
     * Generate a PDF from printable HTML and store on the public disk.
     * Affiliate CTAs stay clickable because hrefs are expanded to absolute https URLs.
     */
    public function generateAndStore(string $html, string $storagePath): string
    {
        $pdfHtml = $this->sanitizeHtmlForPdf($html);

        $pdf = Pdf::loadHTML($pdfHtml)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('dpi', 96);

        Storage::disk('public')->put($storagePath, $pdf->output());

        return $storagePath;
    }

    /**
     * Ensure PDF exists; generate from HTML if missing.
     */
    public function ensurePdf(string $htmlPath, ?string $pdfPath, bool $force = false): ?string
    {
        if (! $force && is_string($pdfPath) && $pdfPath !== '' && Storage::disk('public')->exists($pdfPath)) {
            return $pdfPath;
        }

        if (! Storage::disk('public')->exists($htmlPath)) {
            return null;
        }

        $html = Storage::disk('public')->get($htmlPath);
        $pdfPath = is_string($pdfPath) && $pdfPath !== ''
            ? $pdfPath
            : (preg_replace('/\.html$/', '.pdf', $htmlPath) ?? $htmlPath.'.pdf');

        $this->generateAndStore($html, $pdfPath);

        return $pdfPath;
    }

    /**
     * Rewrite broken # / empty footer CTAs in stored HTML to the campaign tracked affiliate URL,
     * then rebuild the PDF so links open correctly.
     */
    public function repairAffiliateHrefs(string $htmlPath, ?string $pdfPath, string $affiliatePublicUrl): ?string
    {
        if (! Storage::disk('public')->exists($htmlPath)) {
            return null;
        }

        $html = Storage::disk('public')->get($htmlPath);
        $updated = preg_replace(
            '/(<a\b[^>]*\bclass="[^"]*\blm-footer-cta\b[^"]*"[^>]*\bhref=")([^"]*)(")/i',
            '$1'.e($affiliatePublicUrl).'$3',
            $html,
        );
        $updated = is_string($updated) ? $updated : $html;
        $updated = preg_replace(
            '/(href=["\'])(#|javascript:void\(0\);?)(["\'])/i',
            '$1'.e($affiliatePublicUrl).'$3',
            $updated,
        ) ?? $updated;

        if ($updated !== $html) {
            Storage::disk('public')->put($htmlPath, $updated);
        } else {
            return is_string($pdfPath) && $pdfPath !== '' && Storage::disk('public')->exists($pdfPath)
                ? $pdfPath
                : $this->ensurePdf($htmlPath, $pdfPath, force: false);
        }

        return $this->ensurePdf($htmlPath, $pdfPath, force: true);
    }

    /**
     * Dompdf cannot render the browser CSS used by the HTML viewer (gradients, flex,
     * CSS variables, vh min-heights). Swap in a solid-color stylesheet so cover text,
     * blues, and footers stay visible with nothing overlapping.
     */
    public function sanitizeHtmlForPdf(string $html): string
    {
        $colors = $this->extractDesignColors($html);
        $css = $this->dompdfStyles($colors);

        $html = preg_replace('/@import[^;]+;/', '', $html) ?? $html;

        if (preg_match('/<style\b[^>]*>.*?<\/style>/is', $html) === 1) {
            $html = preg_replace(
                '/<style\b[^>]*>.*?<\/style>/is',
                '<style>'.$css.'</style>',
                $html,
                1,
            ) ?? $html;
        } else {
            $html = preg_replace(
                '/<\/head>/i',
                '<style>'.$css.'</style></head>',
                $html,
                1,
            ) ?? $html;
        }

        $base = rtrim((string) config('app.url'), '/');
        $html = preg_replace_callback(
            '/\bhref=(["\'])(\/[^"\']*)\1/i',
            static function (array $match) use ($base): string {
                return 'href='.$match[1].$base.$match[2].$match[1];
            },
            $html,
        ) ?? $html;

        // Dompdf ignores opacity on nested text — force cover copy to pure white.
        $html = preg_replace(
            '/(<p class="lm-cover-(?:label|sub|meta)"[^>]*)>/i',
            '$1 style="color:#ffffff;">',
            $html,
        ) ?? $html;
        $html = preg_replace(
            '/(<h1 class="lm-cover-title"[^>]*)>/i',
            '$1 style="color:#ffffff;">',
            $html,
        ) ?? $html;

        return $html;
    }

    /**
     * @return array{primary: string, secondary: string, accent: string, background: string, text: string}
     */
    protected function extractDesignColors(string $html): array
    {
        $defaults = [
            'primary' => '#4f46e5',
            'secondary' => '#0ea5e9',
            'accent' => '#f59e0b',
            'background' => '#f8fafc',
            'text' => '#1e293b',
        ];

        $fromRoot = false;
        foreach (array_keys($defaults) as $key) {
            if (preg_match('/--'.$key.':\s*(#[0-9a-fA-F]{3,8})\b/', $html, $m) === 1) {
                $defaults[$key] = $this->normalizeHex($m[1]);
                if ($key === 'primary') {
                    $fromRoot = true;
                }
            }
        }

        // Older HTML without :root — take the first solid hex from the cover rule.
        if (! $fromRoot && preg_match('/\.lm-cover[^{]*\{[^}]*background(?:-color)?:\s*(#[0-9a-fA-F]{3,8})/i', $html, $m) === 1) {
            $defaults['primary'] = $this->normalizeHex($m[1]);
        }

        return $defaults;
    }

    protected function normalizeHex(string $hex): string
    {
        $hex = strtolower(trim($hex));
        if (preg_match('/^#([0-9a-f]{3})$/', $hex, $m) === 1) {
            $chars = str_split($m[1]);

            return '#'.$chars[0].$chars[0].$chars[1].$chars[1].$chars[2].$chars[2];
        }

        if (preg_match('/^#([0-9a-f]{6})/', $hex, $m) === 1) {
            return '#'.$m[1];
        }

        return '#4f46e5';
    }

    /**
     * Dompdf-safe layout: page margins + word wrap so content is not clipped on
     * the right, and keep CTA / cards from splitting awkwardly across pages.
     *
     * @param  array{primary: string, secondary: string, accent: string, background: string, text: string}  $colors
     */
    protected function dompdfStyles(array $colors): string
    {
        $primary = $colors['primary'];
        $secondary = $colors['secondary'];
        $accent = $colors['accent'];
        $bg = $colors['background'];
        $text = $colors['text'];
        $muted = '#5c6b7a';
        $border = '#e2e8f0';

        return <<<CSS
@page { margin: 40px 48px; }
* { box-sizing: border-box; }
html, body {
    margin: 0;
    padding: 0;
    font-family: DejaVu Sans, sans-serif;
    color: {$text};
    background: #ffffff;
    line-height: 1.55;
    font-size: 12px;
    word-wrap: break-word;
    overflow-wrap: break-word;
}
.lm-page {
    page-break-after: always;
    page-break-inside: auto;
    padding: 12px 8px 20px;
    margin: 0;
    background: #ffffff;
}
.lm-page:last-child { page-break-after: auto; }
.lm-page.lm-cover,
.lm-cover {
    display: block;
    text-align: center;
    background-color: {$primary};
    background: {$primary};
    color: #ffffff;
    padding: 96px 48px;
    margin: -40px -48px 0;
    min-height: 0;
    page-break-after: always;
    page-break-inside: avoid;
}
.lm-cover-inner {
    width: auto;
    max-width: 100%;
    margin: 0 auto;
    color: #ffffff;
    padding: 0 12px;
}
.lm-cover-label {
    text-transform: uppercase;
    letter-spacing: 2px;
    font-size: 11px;
    font-weight: 700;
    color: #ffffff;
    margin: 0 0 16px;
}
.lm-cover-title {
    font-family: DejaVu Serif, serif;
    font-size: 24px;
    font-weight: 700;
    margin: 16px 12px 14px;
    line-height: 1.3;
    color: #ffffff;
    word-wrap: break-word;
}
.lm-cover-sub {
    font-size: 13px;
    margin: 0 16px 24px;
    font-weight: 400;
    color: #ffffff;
    word-wrap: break-word;
}
.lm-cover-meta {
    font-size: 12px;
    color: #ffffff;
    margin: 0;
}
.lm-page-header {
    border-bottom: 2px solid {$primary};
    padding-bottom: 12px;
    margin-bottom: 18px;
}
.lm-page-num {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: {$muted};
    font-weight: 700;
}
.lm-page-title {
    font-family: DejaVu Serif, serif;
    font-size: 20px;
    font-weight: 700;
    margin: 6px 0 0;
    color: {$primary};
    word-wrap: break-word;
}
.lm-page-body {
    font-family: DejaVu Serif, serif;
    font-size: 12px;
    color: {$text};
    word-wrap: break-word;
}
.lm-page-body p,
.lm-page-body li,
.lm-page-body td,
.lm-page-body th {
    word-wrap: break-word;
    overflow-wrap: break-word;
}
.lm-page-body h2, .lm-page-body h3, .lm-page-body h4 {
    font-family: DejaVu Sans, sans-serif;
    color: {$primary};
    word-wrap: break-word;
}
.lm-intro { font-size: 13px; line-height: 1.6; margin-bottom: 16px; color: {$text}; }
.lm-card {
    background-color: #f8fafc;
    border: 1px solid {$border};
    border-left: 4px solid {$primary};
    border-radius: 6px;
    padding: 12px 14px;
    margin: 14px 0;
    page-break-inside: avoid;
}
.lm-card-accent {
    border-left-color: {$accent};
    background-color: #fffbeb;
}
.lm-card h3, .lm-card h4 {
    margin-top: 0;
    font-family: DejaVu Sans, sans-serif;
    font-size: 13px;
    color: {$primary};
}
.lm-checklist { list-style: disc; padding-left: 18px; margin: 10px 0; }
.lm-checklist li {
    padding: 4px 0;
    border-bottom: 1px solid #f1f5f9;
    color: {$text};
}
.lm-tip {
    background-color: #ecfdf5;
    border-left: 4px solid {$secondary};
    padding: 10px 12px;
    margin: 14px 0;
    font-size: 12px;
    page-break-inside: avoid;
}
.lm-tip strong { color: {$secondary}; }
.lm-warning {
    background-color: #fffbeb;
    border-left: 4px solid {$accent};
    padding: 10px 12px;
    margin: 14px 0;
    page-break-inside: avoid;
}
.lm-steps { list-style: decimal; padding-left: 20px; margin: 12px 0; }
.lm-steps li {
    padding: 8px 10px;
    margin-bottom: 8px;
    background-color: #f8fafc;
    border-radius: 6px;
    color: {$text};
    page-break-inside: avoid;
}
.lm-stat-grid { width: 100%; margin: 14px 0; page-break-inside: avoid; }
.lm-stat {
    display: inline-block;
    width: 30%;
    vertical-align: top;
    background-color: {$bg};
    border-radius: 6px;
    padding: 10px;
    text-align: center;
    border-top: 3px solid {$accent};
    margin-right: 2%;
}
.lm-stat strong { display: block; font-size: 18px; color: {$primary}; font-family: DejaVu Sans, sans-serif; }
.lm-stat span { font-size: 9px; color: {$muted}; text-transform: uppercase; }
.lm-quote {
    border-left: 4px solid {$accent};
    padding: 8px 14px;
    margin: 16px 0;
    font-style: italic;
    font-size: 12px;
    color: {$muted};
    page-break-inside: avoid;
}
.lm-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    margin: 12px 0;
    font-size: 10px;
    font-family: DejaVu Sans, sans-serif;
    page-break-inside: avoid;
}
.lm-table th { background-color: {$primary}; color: #ffffff; padding: 6px 8px; text-align: left; word-wrap: break-word; }
.lm-table td { padding: 6px 8px; border-bottom: 1px solid {$border}; color: {$text}; word-wrap: break-word; }
.lm-exercise {
    background-color: #f0fdf4;
    border: 2px dashed #86efac;
    border-radius: 8px;
    padding: 12px 14px;
    margin: 14px 0;
    page-break-inside: avoid;
}
.lm-exercise h4 { margin-top: 0; color: #15803d; font-family: DejaVu Sans, sans-serif; }
.lm-footer {
    margin-top: 22px;
    padding-top: 12px;
    border-top: 2px solid {$border};
    text-align: center;
    clear: both;
    page-break-inside: avoid;
}
.lm-footer p {
    font-size: 11px;
    color: {$muted};
    margin: 0 8px 10px;
    word-wrap: break-word;
}
.lm-footer-cta {
    display: block;
    width: auto;
    max-width: 88%;
    margin: 0 auto;
    background-color: {$primary};
    color: #ffffff !important;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 11px;
    font-family: DejaVu Sans, sans-serif;
    word-wrap: break-word;
    white-space: normal;
    page-break-inside: avoid;
}
CSS;
    }
}
