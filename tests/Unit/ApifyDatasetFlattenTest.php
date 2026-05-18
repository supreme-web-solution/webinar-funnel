<?php

namespace Tests\Unit;

use App\Services\ApifyService;
use PHPUnit\Framework\TestCase;

class ApifyDatasetFlattenTest extends TestCase
{
    public function test_flattens_list_and_wrapped_payloads(): void
    {
        $this->assertSame([], ApifyService::flattenDatasetItems(null));

        $this->assertCount(2, ApifyService::flattenDatasetItems([
            ['id' => '1'],
            ['id' => '2'],
        ]));

        $this->assertSame(
            [['title' => 'A']],
            ApifyService::flattenDatasetItems(['data' => [['title' => 'A']]])
        );

        $this->assertSame(
            [['title' => 'B']],
            ApifyService::flattenDatasetItems(['title' => 'B'])
        );
    }
}
