<?php

namespace Tests\Unit\Promotion;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\Promotion\PromotionFunnelContextBuilder;
use App\Services\Promotion\PromotionTopicSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionTopicSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_topics_use_template_bullets_not_generic_webinar_title(): void
    {
        config(['services.openai.api_key' => '']);

        $user = User::factory()->create();
        $template = Template::query()->create([
            'name' => 'GuruOS Offer',
            'slug' => 'guruos-offer',
            'category' => 'marketing',
            'conversion_style' => 'evergreen',
            'is_active' => true,
            'sort_order' => 1,
            'suggested_keywords' => ['ai business', 'course creation'],
        ]);

        TemplateVersion::query()->create([
            'template_id' => $template->id,
            'version' => 1,
            'is_current' => true,
            'optin_schema' => [
                'hero' => [
                    'subheadline' => 'Discover the AI operating system that builds courses and funnels for you.',
                ],
                'what_youll_discover' => [
                    'How to use AI agents to launch digital products faster',
                    'How beginners build scalable info businesses without showing their face',
                ],
            ],
            'webinar_schema' => [
                'title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'description' => "Offer summary\n\nDiscover the AI operating system.\n\nWhat You'll Discover On This FREE Training:\n- Bullet from description",
            ],
            'default_settings' => [],
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'GuruOS Funnel',
            'slug' => 'guruos-funnel',
            'status' => 'draft',
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
            'webinar_description' => 'Training description',
            'chat_mode' => 'simulated',
            'allow_replay' => true,
        ]);

        $topics = app(PromotionTopicSuggestionService::class)->generate(
            $funnel->load(['settings', 'template.versions', 'keywords']),
            6,
        );

        $this->assertNotEmpty($topics);
        $joined = mb_strtolower(implode(' | ', array_column($topics, 'topic')));
        $this->assertStringNotContainsString('watch this training completely', $joined);
        $this->assertStringContainsString('ai agents', $joined);
    }

    public function test_context_builder_uses_template_name_as_product(): void
    {
        $user = User::factory()->create();
        $template = Template::query()->create([
            'name' => 'Agentic Agency Offer',
            'slug' => 'agentic-agency-offer',
            'category' => 'marketing',
            'conversion_style' => 'lead-gen',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        TemplateVersion::query()->create([
            'template_id' => $template->id,
            'version' => 1,
            'is_current' => true,
            'optin_schema' => [
                'what_youll_discover' => ['How to automate local business lead follow-up'],
            ],
            'webinar_schema' => [],
            'default_settings' => [],
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'My Funnel',
            'slug' => 'my-funnel',
            'status' => 'draft',
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
            'chat_mode' => 'simulated',
            'allow_replay' => true,
        ]);

        $context = app(PromotionFunnelContextBuilder::class)->build($funnel->load(['settings', 'template.versions']));

        $this->assertSame('Agentic Agency', $context['product_name']);
        $this->assertCount(1, $context['bullet_points']);
        $this->assertTrue(app(PromotionFunnelContextBuilder::class)->isGenericWebinarTitle($context['product_name']) === false);
    }
}
