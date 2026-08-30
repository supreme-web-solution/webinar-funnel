<?php

namespace App\Services\Campaigns;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class LeadMagnetPdfService
{
    /**
     * Generate a PDF from printable HTML and store on the public disk.
     */
    public function generateAndStore(string $html, string $storagePath): string
    {
        $pdfHtml = $this->sanitizeHtmlForPdf($html);

        $pdf = Pdf::loadHTML($pdfHtml)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'DejaVu Sans');

        Storage::disk('public')->put($storagePath, $pdf->output());

        return $storagePath;
    }

    /**
     * Ensure PDF exists; generate from HTML if missing.
     */
    public function ensurePdf(string $htmlPath, ?string $pdfPath): ?string
    {
        if (is_string($pdfPath) && $pdfPath !== '' && Storage::disk('public')->exists($pdfPath)) {
            return $pdfPath;
        }

        if (! Storage::disk('public')->exists($htmlPath)) {
            return null;
        }

        $html = Storage::disk('public')->get($htmlPath);
        $pdfPath = preg_replace('/\.html$/', '.pdf', $htmlPath) ?? $htmlPath.'.pdf';

        $this->generateAndStore($html, $pdfPath);

        return $pdfPath;
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

        return $html;
    }
}
