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
            ->setOption('defaultFont', 'DejaVu Sans');

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

    protected function sanitizeHtmlForPdf(string $html): string
    {
        // Dompdf cannot fetch Google Fonts — use system-friendly stack.
        $html = preg_replace('/@import[^;]+;/', '', $html) ?? $html;
        $html = str_replace(
            ["font-family: 'Inter', system-ui, sans-serif", "font-family: 'Source Serif 4', Georgia, serif"],
            ['font-family: DejaVu Sans, sans-serif', 'font-family: DejaVu Serif, serif'],
            $html
        );

        $base = rtrim((string) config('app.url'), '/');
        $html = preg_replace_callback(
            '/\bhref=(["\'])(\/[^"\']*)\1/i',
            static function (array $match) use ($base): string {
                return 'href='.$match[1].$base.$match[2].$match[1];
            },
            $html,
        ) ?? $html;

        return $html;
    }
}
