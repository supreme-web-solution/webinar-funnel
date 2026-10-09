<?php

namespace Tests\Feature\Campaigns;

use App\Jobs\Campaigns\RunCampaignQuickStartJob;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\CampaignPage;
use App\Models\User;
use App\Services\Ai\OpenRouterService;
use App\Services\Campaigns\CampaignGenerationProgressService;
use App\Services\Campaigns\LeadMagnetGeneratorService;
use App\Services\Campaigns\TrackedLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class QuizAndPendingAffiliateTest extends TestCase
{
    use RefreshDatabase;

    public function test_quiz_page_exposes_lead_magnet_title(): void
    {
        $user = User::factory()->create(['username' => 'quizzer']);
        $campaign = $this->publishedCampaign($user, 'kdp');

        CampaignPage::query()->create([
            'campaign_id' => $campaign->id,
            'page_type' => 'quiz',
            'content' => ['title' => 'Are you ready?', 'questions' => [['question' => 'Q1', 'options' => ['A', 'B']]]],
        ]);
        CampaignPage::query()->create([
            'campaign_id' => $campaign->id,
            'page_type' => 'lead_magnet',
            'content' => ['title' => 'KDP Launch Checklist'],
        ]);

        $this->get(route('public.campaign.page', ['username' => 'quizzer', 'slug' => 'kdp', 'page' => 'quiz']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/campaign/Quiz')
                ->where('content.lead_magnet_title', 'KDP Launch Checklist'));
    }

    public function test_quiz_optin_saves_answers_and_redirects_to_download(): void
    {
        $user = User::factory()->create(['username' => 'quizzer']);
        $campaign = $this->publishedCampaign($user, 'kdp');

        $response = $this->post(route('public.campaign.optin', ['username' => 'quizzer', 'slug' => 'kdp']), [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'quiz_answers' => [
                ['question' => 'Biggest challenge?', 'answer' => 'Finding ideas'],
            ],
        ]);

        $lead = CampaignLead::query()->where('campaign_id', $campaign->id)->firstOrFail();

        $response->assertRedirect(route('public.campaign.page', [
            'username' => 'quizzer',
            'slug' => 'kdp',
            'page' => 'thankyou',
            'dl' => $lead->download_token,
        ]));
        $this->assertSame('quiz', $lead->metadata['source']);
        $this->assertSame('Finding ideas', $lead->metadata['quiz_answers'][0]['answer']);
    }

    public function test_campaign_links_can_be_created_before_hop_link_and_are_repointed_when_saved(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'No Link Yet',
            'slug' => 'no-link-yet',
            'type' => 'sales',
            'status' => 'draft',
        ]);

        $link = app(TrackedLinkService::class)->createForCampaignAffiliate($campaign, 'Lead magnet footer CTA');
        $this->assertSame('#', $link->destination_url);

        $this->actingAs($user)
            ->patch(route('campaigns.update', $campaign), ['affiliate_link' => 'https://hop.example.com/abc'])
            ->assertRedirect();

        $this->assertSame('https://hop.example.com/abc', $link->fresh()->destination_url);
    }

    public function test_lead_magnet_generation_runs_without_hop_link(): void
    {
        $this->mock(OpenRouterService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('leadMagnetTimeout')->andReturn(30);
            $mock->shouldReceive('modelFor')->andReturn('test-model');
            $mock->shouldReceive('chatJsonWithFallback')->andReturn(['ok' => false, 'data' => null, 'error' => 'AI offline']);
        });

        $user = User::factory()->create();
        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'No Link',
            'slug' => 'no-link',
            'type' => 'sales',
            'status' => 'draft',
        ]);
        CampaignPage::query()->create([
            'campaign_id' => $campaign->id,
            'page_type' => 'lead_magnet',
            'content' => ['suggestions' => [['id' => 'lm1', 'title' => 'Guide', 'format' => 'ebook']]],
        ]);

        $result = app(LeadMagnetGeneratorService::class)->generate($campaign, 'lm1');

        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('hop link', (string) $result['error']);
        $this->assertDatabaseHas('tracked_links', [
            'campaign_id' => $campaign->id,
            'label' => 'Lead magnet footer CTA',
            'destination_url' => '#',
        ]);
    }

    public function test_saving_hop_link_resumes_failed_quick_start(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'Quick',
            'slug' => 'quick',
            'type' => 'sales',
            'status' => 'draft',
            'meta' => ['quick_start' => true],
        ]);
        app(CampaignGenerationProgressService::class)->fail($campaign, 'Something broke.');

        $this->actingAs($user)
            ->patch(route('campaigns.update', $campaign), ['affiliate_link' => 'https://hop.example.com/abc'])
            ->assertRedirect();

        Queue::assertPushed(RunCampaignQuickStartJob::class, fn ($job) => $job->campaignId === $campaign->id);
        $this->assertSame('running', $campaign->fresh()->meta['generation']['status']);
    }

    public function test_saving_hop_link_does_not_restart_manual_campaigns(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'Manual',
            'slug' => 'manual',
            'type' => 'sales',
            'status' => 'draft',
        ]);
        app(CampaignGenerationProgressService::class)->fail($campaign, 'Something broke.');

        $this->actingAs($user)
            ->patch(route('campaigns.update', $campaign), ['affiliate_link' => 'https://hop.example.com/abc']);

        Queue::assertNotPushed(RunCampaignQuickStartJob::class);
        $this->assertSame('failed', $campaign->fresh()->meta['generation']['status']);
    }

    protected function publishedCampaign(User $user, string $slug): Campaign
    {
        return Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'Amazon KDP',
            'slug' => $slug,
            'type' => 'sales',
            'status' => 'published',
            'affiliate_link' => 'https://hop.example.com/kdp',
        ]);
    }
}
