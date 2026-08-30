<?php

namespace Tests\Unit\Campaigns;

use App\Services\Campaigns\OfferScoringService;
use PHPUnit\Framework\TestCase;

class OfferScoringServiceTest extends TestCase
{
    public function test_ranks_clickbank_offers_by_composite_score(): void
    {
        $service = new OfferScoringService;

        $result = $service->scoreAndRank([
            [
                'title' => 'Low performer',
                'marketplace' => 'clickbank',
                'url' => 'https://example.com/a',
                'gravity_hint' => '10',
                'epc_hint' => '$0.20',
            ],
            [
                'title' => 'Hot offer',
                'marketplace' => 'clickbank',
                'url' => 'https://example.com/b',
                'gravity_hint' => '120',
                'epc_hint' => '$2.10',
                'refund_rate' => '4',
            ],
        ]);

        $this->assertSame('Hot offer', $result['results'][0]['title']);
        $this->assertSame('Hot offer', $result['top_pick']['title'] ?? null);
        $this->assertGreaterThan($result['results'][1]['score'], $result['results'][0]['score']);
    }

    public function test_scores_html_marketplace_listings_without_gravity(): void
    {
        $service = new OfferScoringService;

        $result = $service->scoreAndRank([
            [
                'title' => 'JVZoo listing',
                'marketplace' => 'jvzoo',
                'url' => 'https://example.com/j',
                'trend' => 'rising',
            ],
        ]);

        $this->assertGreaterThanOrEqual(45, $result['results'][0]['score']);
        $this->assertNotEmpty($result['results'][0]['promote_reason']);
    }
}
