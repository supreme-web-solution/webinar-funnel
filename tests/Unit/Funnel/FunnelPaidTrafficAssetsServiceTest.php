<?php

namespace Tests\Unit\Funnel;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Template;
use App\Models\User;
use App\Services\Funnel\FunnelPaidTrafficAssetsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelPaidTrafficAssetsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_rotating_preview_and_drive_url_for_template_index(): void
    {
        $user = User::factory()->create();

        $template = Template::query()->create([
            'name' => 'LocalMator Offer',
            'slug' => 'localmator-offer',
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 13,
            'paid_traffic_drive_url' => 'https://drive.google.com/drive/folders/example-localmator',
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'Local Mator Funnel',
            'slug' => 'localmator',
            'status' => 'published',
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
        ]);

        $assets = app(FunnelPaidTrafficAssetsService::class)->resolveForFunnel($funnel);

        $this->assertNotNull($assets);
        $this->assertSame(13, $assets['template_index']);
        $this->assertSame('https://drive.google.com/drive/folders/example-localmator', $assets['drive_url']);
        $this->assertSame('/funnel-files/previews/preview-1.png', $assets['poster_url']);
    }

    public function test_preview_rotates_across_three_shared_images(): void
    {
        $user = User::factory()->create();

        $template = Template::query()->create([
            'name' => 'TokPrime Offer',
            'slug' => 'tokprime-offer',
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 30,
            'paid_traffic_drive_url' => 'https://drive.google.com/drive/folders/example-tokprime',
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'TokPrime Funnel',
            'slug' => 'tokprime',
            'status' => 'published',
        ]);

        $assets = app(FunnelPaidTrafficAssetsService::class)->resolveForFunnel($funnel);

        $this->assertNotNull($assets);
        $this->assertSame('/funnel-files/previews/preview-3.png', $assets['poster_url']);
    }
}
