<?php

namespace Tests\Feature;

use App\Services\Campaigns\MarketplaceHtmlSearchService;
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
        config([
            'services.marketplace.trending_keywords' => ['ai software', 'keto'],
            'services.marketplace.clickbank_apify_enabled' => false,
        ]);
    }

    public function test_command_saves_results_that_survive_a_cache_clear(): void
    {
        $this->mockListings(
            jvzoo: [$this->row('AI Writer Pro', 'jvzoo')],
            warriorPlus: [$this->row('Keto Kitchen Secrets', 'warriorplus')],
        );

        $this->artisan('marketplace:refresh-trending')
            ->expectsOutputToContain('Saved 2 marketplace offers')
            ->assertSuccessful();

        Cache::flush();

        $snapshot = app(MarketplaceTrendingStore::class)->get();
        $this->assertNotNull($snapshot);
        $this->assertCount(2, $snapshot['listings']);
        $this->assertEqualsCanonicalizing(['AI Writer Pro', 'Keto Kitchen Secrets'], array_column($snapshot['results'], 'title'));
    }

    public function test_listings_are_fetched_once_not_per_keyword(): void
    {
        $html = $this->mockListings(jvzoo: [$this->row('AI Writer Pro', 'jvzoo')], warriorPlus: []);

        $this->artisan('marketplace:refresh-trending')->assertSuccessful();

        $html->shouldHaveReceived('jvzooListings')->once();
        $html->shouldHaveReceived('warriorPlusListings')->once();
    }

    public function test_empty_scan_keeps_previous_results(): void
    {
        app(MarketplaceTrendingStore::class)->put([
            'results' => [$this->row('Previous Offer', 'jvzoo')],
            'top_pick' => null,
            'sources' => ['jvzoo_html'],
            'refreshed_at' => now()->subDay()->toIso8601String(),
        ]);

        $this->mockListings(jvzoo: [], warriorPlus: []);

        $this->artisan('marketplace:refresh-trending')->assertFailed();

        $this->assertSame('Previous Offer', app(MarketplaceTrendingStore::class)->get()['results'][0]['title']);
    }

    public function test_keyword_search_filters_todays_listings(): void
    {
        $html = $this->mockListings(
            jvzoo: [$this->row('AI Writer Pro', 'jvzoo'), $this->row('Email Marketing Machine', 'jvzoo')],
            warriorPlus: [$this->row('Keto Kitchen Secrets', 'warriorplus'), $this->row('Video Deck', 'warriorplus')],
        );
        $this->artisan('marketplace:refresh-trending')->assertSuccessful();

        $search = app(MarketplaceOfferSearchService::class);

        $keto = $search->search('keto');
        $this->assertTrue($keto['ok']);
        $this->assertSame(['Keto Kitchen Secrets'], array_column($keto['results'], 'title'));

        $email = $search->search('email marketing', 'jvzoo');
        $this->assertSame(['Email Marketing Machine'], array_column($email['results'], 'title'));

        $none = $search->search('dog training');
        $this->assertFalse($none['ok']);
        $this->assertStringContainsString('No offers matching "dog training"', $none['error']);

        $html->shouldHaveReceived('jvzooListings')->once();
    }

    public function test_short_terms_match_whole_words_only(): void
    {
        $rows = [
            $this->row('The #1 AI Tool For Affiliates', 'jvzoo'),
            $this->row('Aim Higher Coaching', 'jvzoo'),
        ];

        $matches = app(MarketplaceOfferSearchService::class)->matchListings($rows, 'ai software');

        $this->assertSame(['The #1 AI Tool For Affiliates'], array_column($matches, 'title'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $jvzoo
     * @param  array<int, array<string, mixed>>  $warriorPlus
     */
    private function mockListings(array $jvzoo, array $warriorPlus): MockInterface
    {
        return $this->mock(MarketplaceHtmlSearchService::class, function (MockInterface $mock) use ($jvzoo, $warriorPlus): void {
            $mock->shouldReceive('jvzooListings')->andReturn($jvzoo);
            $mock->shouldReceive('warriorPlusListings')->andReturn($warriorPlus);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $title, string $marketplace): array
    {
        return [
            'title' => $title,
            'marketplace' => $marketplace,
            'url' => 'https://example.com/'.md5($title),
            'gravity_hint' => '',
            'epc_hint' => '',
            'why' => 'Listed today',
            'source' => $marketplace.'_html',
        ];
    }
}
