<?php

namespace Tests\Unit\Campaigns;

use App\Models\Campaign;
use App\Models\User;
use App\Services\Campaigns\CampaignKnowledgeContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignKnowledgeContextServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_traffic_keywords_and_promotion_seeds_use_campaign_knowledge(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'AI Email Campaign',
            'slug' => 'ai-email-campaign',
            'type' => 'sales',
            'status' => 'draft',
            'offer_data' => [
                'product_name' => 'Automated Commission Machine',
                'niche' => 'Affiliate Marketing',
            ],
            'knowledge' => [
                'status' => 'ready',
                'pass1' => [
                    'product_summary' => 'Paste any affiliate URL and launch a full funnel in minutes.',
                    'unique_mechanism' => 'URL-to-funnel AI builder',
                    'target_audience' => ['busy affiliate marketers', 'side hustlers'],
                    'core_pain_points' => ['Building funnels takes too long'],
                    'hook_angles' => [
                        'Build your email list in minutes!',
                        'No tech skills? No problem!',
                    ],
                    'objections' => [
                        ['objection' => 'I am not technical enough', 'rebuttal' => 'The tool builds everything for you'],
                    ],
                ],
                'pass2' => [
                    'squeeze_page_strategy' => [
                        'headline' => 'Get the checklist',
                        'subheadline' => 'Turn any offer into a lead magnet',
                        'bullets' => ['How to paste a URL and get a squeeze page'],
                    ],
                    'email_sequence_plan' => [
                        ['day' => 1, 'angle' => 'Quick win from the checklist', 'subject_hint' => 'Your 3-minute funnel'],
                    ],
                ],
            ],
        ]);

        $service = app(CampaignKnowledgeContextService::class);

        $keywords = $service->trafficKeywordSuggestions($campaign);
        $this->assertContains('Automated Commission Machine', $keywords);
        $this->assertContains('Affiliate Marketing', $keywords);

        $seeds = $service->promotionTopicSeeds($campaign);
        $joined = implode(' | ', $seeds);
        $this->assertStringContainsString('Build your email list in minutes!', $joined);
        $this->assertStringContainsString('How to paste a URL and get a squeeze page', $joined);
        $this->assertSame('Automated Commission Machine', $service->productName($campaign));
    }
}
