<?php

namespace Tests\Unit;

use App\Services\Campaigns\LeadMagnetPdfService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadMagnetPdfServiceTest extends TestCase
{
    public function test_pdf_html_uses_solid_cover_colors_instead_of_gradients(): void
    {
        config(['app.url' => 'https://autoaffiliate360.com']);

        $html = '<!DOCTYPE html><html><head><style>'
            .':root { --primary: #4376b3; --secondary: #0ea5e9; --accent: #f59e0b; --bg: #f8fafc; --text: #1e293b; }'
            .'.lm-cover { display: flex; background: linear-gradient(160deg, var(--primary) 0%, #2d4a6f 100%); color: #fff; }'
            .'</style></head><body>'
            .'<section class="lm-page lm-cover"><div class="lm-cover-inner">'
            .'<p class="lm-cover-label">Free Guide</p>'
            .'<h1 class="lm-cover-title">Title</h1>'
            .'<a class="lm-footer-cta" href="/r/x4pm2bsa">Get the full solution →</a>'
            .'</div></section>'
            .'</body></html>';

        $sanitized = app(LeadMagnetPdfService::class)->sanitizeHtmlForPdf($html);

        $this->assertStringContainsString('background-color: #4376b3', $sanitized);
        $this->assertStringContainsString('@page { margin: 48px 54px; }', $sanitized);
        $this->assertStringContainsString('height: 297mm', $sanitized);
        $this->assertStringContainsString('lm-cover-table', $sanitized);
        $this->assertStringContainsString('vertical-align: middle', $sanitized);
        $this->assertStringContainsString('word-wrap: break-word', $sanitized);
        $this->assertStringContainsString('table-layout: fixed', $sanitized);
        $this->assertStringContainsString('max-width: 92%', $sanitized);
        $this->assertStringNotContainsString('linear-gradient', $sanitized);
        $this->assertStringNotContainsString('var(--primary)', $sanitized);
        $this->assertStringNotContainsString('min-height: 90vh', $sanitized);
        $this->assertStringNotContainsString('display: flex', $sanitized);
        $this->assertStringContainsString('color:#ffffff', $sanitized);
        $this->assertStringContainsString('href="https://autoaffiliate360.com/r/x4pm2bsa"', $sanitized);
    }

    public function test_pdf_html_expands_relative_affiliate_links(): void
    {
        config(['app.url' => 'https://autoaffiliate360.com']);
        Storage::fake('public');

        $html = '<!DOCTYPE html><html><head><style>:root { --primary: #4f46e5; }</style></head><body>'
            .'<a class="lm-footer-cta" href="/r/x4pm2bsa">Get the full solution →</a>'
            .'</body></html>';

        Storage::disk('public')->put('campaigns/demo/lead-magnet.html', $html);

        $service = app(LeadMagnetPdfService::class);
        $service->generateAndStore($html, 'campaigns/demo/lead-magnet.pdf');

        $this->assertTrue(Storage::disk('public')->exists('campaigns/demo/lead-magnet.pdf'));
        $this->assertGreaterThan(100, strlen(Storage::disk('public')->get('campaigns/demo/lead-magnet.pdf')));
    }

    public function test_repair_rewrites_hash_footer_ctas(): void
    {
        config(['app.url' => 'https://autoaffiliate360.com']);
        Storage::fake('public');

        $html = '<!DOCTYPE html><html><body>'
            .'<a class="lm-footer-cta" href="#">Broken CTA →</a>'
            .'</body></html>';
        Storage::disk('public')->put('campaigns/demo/lead-magnet.html', $html);

        app(LeadMagnetPdfService::class)->repairAffiliateHrefs(
            'campaigns/demo/lead-magnet.html',
            'campaigns/demo/lead-magnet.pdf',
            'https://autoaffiliate360.com/r/goodcode',
        );

        $updated = Storage::disk('public')->get('campaigns/demo/lead-magnet.html');
        $this->assertStringContainsString('href="https://autoaffiliate360.com/r/goodcode"', $updated);
        $this->assertStringNotContainsString('href="#"', $updated);
    }
}
