<?php

namespace Tests\Unit\Campaigns;

use App\Services\Campaigns\SalesPageFetcherService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SalesPageFetcherServiceTest extends TestCase
{
    public function test_jina_reader_is_tried_first(): void
    {
        config(['services.jina_reader.enabled' => true]);

        Http::fake([
            'r.jina.ai/*' => Http::response('# Product Title\n\nThis is a long enough sales page body for extraction to proceed.', 200),
        ]);

        $result = app(SalesPageFetcherService::class)->fetch('https://example.com/sales');

        $this->assertTrue($result['ok']);
        $this->assertSame('jina', $result['method']);
        $this->assertStringContainsString('Product Title', $result['text']);
    }

    public function test_falls_back_to_direct_when_jina_fails(): void
    {
        config(['services.jina_reader.enabled' => true]);

        Http::fake([
            'r.jina.ai/*' => Http::response('', 500),
            'example.com/*' => Http::response('<html><body><h1>Direct fetch headline</h1><p>Enough text here to pass the minimum length check easily.</p></body></html>', 200),
        ]);

        $result = app(SalesPageFetcherService::class)->fetch('https://example.com/sales');

        $this->assertTrue($result['ok']);
        $this->assertSame('direct', $result['method']);
    }
}
