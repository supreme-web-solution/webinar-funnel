<?php

namespace Tests\Feature;

use App\Services\Campaigns\MarketplaceOfferSearchService;
use App\Services\Campaigns\MarketplaceTrendingStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class MarketplaceTrendingRefreshTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::flush();
        config(['services.marketplace.trending_keywords' => ['ai software']]);
    }

    public function test_command_saves_results_that_survive_a_cache_clear(): void
    {
        $this->mockSearch([
            ['title' => 'AI Writer Pro', 'marketplace' => 'jvzoo', 'url' => 'https://jvzoomarket.com/productlibrary/review/1'],
        ]);

        $this->artisan('marketplace:refresh-trending')->assertSuccessful();

        Cache::flush();

        $snapshot = app(MarketplaceTrendingStore::class)->get();
        $this->assertNotNull($snapshot);
        $this->assertSame('AI Writer Pro', $snapshot['results'][0]['title']);
    }

    public function test_empty_scan_keeps_previous_results(): void
    {
        app(MarketplaceTrendingStore::class)->put([
            'results' => [['title' => 'Previous Offer', 'marketplace' => 'jvzoo', 'url' => 'https://example.com']],
            'top_pick' => null,
            'sources' => ['jvzoo_html'],
            'refreshed_at' => now()->subDay()->toIso8601String(),
        ]);

        $this->mockSearch([], 'ClickBank: Monthly usage hard limit exceeded');

        $this->artisan('marketplace:refresh-trending')
            ->expectsOutputToContain('Monthly usage hard limit exceeded')
            ->assertFailed();

        $this->assertSame('Previous Offer', app(MarketplaceTrendingStore::class)->get()['results'][0]['title']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function mockSearch(array $results, ?string $error = null): void
    {
        $this->partialMock(MarketplaceOfferSearchService::class, function (MockInterface $mock) use ($results, $error): void {
            $mock->shouldReceive('search')->andReturn([
                'ok' => $results !== [],
                'results' => $results,
                'search_links' => [],
                'error' => $error,
                'sources' => $results !== [] ? ['jvzoo_html'] : [],
                'top_pick' => null,
            ]);
        });
    }
}
