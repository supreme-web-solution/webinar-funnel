<?php

namespace Tests\Unit;

use App\Services\RedditService;
use Tests\TestCase;

class RedditServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        config([
            'services.apify.default_sort' => 'new',
            'services.apify.default_time' => 'year',
            'services.apify.max_items_per_search' => 25,
        ]);

        parent::tearDown();
    }

    public function test_search_input_includes_time_and_sort_from_config(): void
    {
        config([
            'services.apify.default_sort' => 'new',
            'services.apify.default_time' => 'year',
            'services.apify.max_items_per_search' => 10,
        ]);

        $input = RedditService::searchInputForKeyword('digital marketing');

        $this->assertSame(['digital marketing'], $input['searches']);
        $this->assertSame('new', $input['sort']);
        $this->assertSame('year', $input['time']);
        $this->assertSame(10, $input['maxItems']);
        $this->assertTrue($input['searchPosts']);
        $this->assertFalse($input['searchComments']);
    }

    public function test_search_input_falls_back_for_invalid_time_and_sort(): void
    {
        config([
            'services.apify.default_sort' => 'not-a-sort',
            'services.apify.default_time' => 'not-a-time',
        ]);

        $input = RedditService::searchInputForKeyword('test');

        $this->assertSame('new', $input['sort']);
        $this->assertSame('year', $input['time']);
    }
}
