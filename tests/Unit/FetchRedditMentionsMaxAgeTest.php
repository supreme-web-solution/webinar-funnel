<?php

namespace Tests\Unit;

use App\Jobs\FetchRedditMentions;
use App\Models\Funnel;
use App\Models\Keyword;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class FetchRedditMentionsMaxAgeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config(['services.apify.max_post_age_days' => 90]);

        parent::tearDown();
    }

    private function keywordForTest(): Keyword
    {
        $user = User::factory()->create();
        $template = Template::query()->create([
            'name' => 'T',
            'slug' => 't-'.uniqid(),
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'F',
            'slug' => 'f-'.uniqid(),
            'status' => 'draft',
        ]);

        return Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'test',
            'is_active' => true,
            'platforms' => ['reddit'],
        ]);
    }

    public function test_skips_posts_older_than_configured_max_age_days(): void
    {
        config(['services.apify.max_post_age_days' => 90]);

        $job = new FetchRedditMentions($this->keywordForTest());

        $method = new ReflectionMethod(FetchRedditMentions::class, 'olderThanConfiguredMaxAge');
        $method->setAccessible(true);

        $tooOld = now()->subDays(91)->timestamp;
        $recent = now()->subDays(10)->timestamp;

        $this->assertTrue($method->invoke($job, $tooOld));
        $this->assertFalse($method->invoke($job, $recent));
    }

    public function test_max_age_check_disabled_when_zero(): void
    {
        config(['services.apify.max_post_age_days' => 0]);

        $job = new FetchRedditMentions($this->keywordForTest());

        $method = new ReflectionMethod(FetchRedditMentions::class, 'olderThanConfiguredMaxAge');
        $method->setAccessible(true);

        $tooOld = now()->subYears(5)->timestamp;

        $this->assertFalse($method->invoke($job, $tooOld));
    }
}
