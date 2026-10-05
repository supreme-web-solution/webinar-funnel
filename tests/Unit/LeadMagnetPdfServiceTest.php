<?php

namespace Tests\Unit;

use App\Services\Campaigns\LeadMagnetPdfService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadMagnetPdfServiceTest extends TestCase
{
    public function test_pdf_html_expands_relative_affiliate_links(): void
    {
        config(['app.url' => 'https://autoaffiliate360.com']);
        Storage::fake('public');

        $html = '<!DOCTYPE html><html><body>'
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
