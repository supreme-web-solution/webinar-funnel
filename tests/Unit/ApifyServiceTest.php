<?php

namespace Tests\Unit;

use App\Services\ApifyService;
use PHPUnit\Framework\TestCase;

class ApifyServiceTest extends TestCase
{
    public function test_normalizes_slash_actor_id_to_tilde_format(): void
    {
        $this->assertSame(
            'streamers~youtube-scraper',
            ApifyService::normalizeActorIdForApi('streamers/youtube-scraper')
        );

        $this->assertSame(
            'practicaltools~apify-reddit-api',
            ApifyService::normalizeActorIdForApi('practicaltools/apify-reddit-api')
        );
    }

    public function test_leaves_tilde_format_unchanged(): void
    {
        $this->assertSame(
            'patient_discovery~twitter-search',
            ApifyService::normalizeActorIdForApi('patient_discovery~twitter-search')
        );
    }
}
