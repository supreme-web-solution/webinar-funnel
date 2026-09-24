<?php

namespace App\Services\Content;

use App\Models\Campaign;
use App\Models\ContentEmployeePlan;
use App\Models\ContentEmployeePlanItem;
use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Models\User;
use App\Models\UserTrafficProfile;
use App\Services\Campaigns\CampaignKnowledgeContextService;
use App\Services\Campaigns\CampaignTrafficHubService;
use App\Services\Promotion\PromotionCtaResolverService;
use App\Services\Promotion\PromotionFunnelContextBuilder;
use App\Services\Promotion\PromotionGenerationDispatcher;
use App\Services\Promotion\PromotionPlatformCatalog;
use App\Services\Promotion\PromotionTopicSuggestionService;
use App\Services\Traffic\StandaloneTrafficWorkspaceService;
use App\Services\Traffic\UserTrafficProfileService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ContentEmployeePlanService
{
    public function __construct(
        private readonly PlatformFormatCatalog $formatCatalog,
        private readonly UserTrafficProfileService $profileService,
        private readonly ContentEmployeeFormatSelectorService $formatSelector,
        private readonly CampaignKnowledgeContextService $knowledge,
        private readonly CampaignTrafficHubService $trafficHub,
        private readonly StandaloneTrafficWorkspaceService $workspace,
        private readonly PromotionCtaResolverService $ctaResolver,
        private readonly PromotionPlatformCatalog $platformCatalog,
        private readonly PromotionGenerationDispatcher $generationDispatcher,
        private readonly PromotionTopicSuggestionService $topicSuggestions,
        private readonly PromotionFunnelContextBuilder $funnelContextBuilder,
    ) {}

    public function generateWeeklyPlan(
        User $user,
        ?Campaign $campaign,
        ?Carbon $weekStart = null,
        ?bool $autoSelectFormats = null,
        ?string $planningBrief = null,
    ): ContentEmployeePlan {
        $weekStart = ($weekStart ?? Carbon::now()->startOfWeek())->copy()->startOfDay();
        $profile = $this->profileService->getOrCreate($user);
        $funnel = $this->resolveFunnel($user, $campaign);
        $planningBrief = trim((string) ($planningBrief ?? ($profile->meta['planning_brief'] ?? '')));

        if ($campaign === null && $planningBrief !== '') {
            $this->applyPlanningBriefToStandaloneFunnel($funnel, $planningBrief);
        }

        $connected = $this->platformCatalog->connectedPlatformKeys((int) $user->id);
        if ($connected === []) {
            throw ValidationException::withMessages([
                'plan' => 'Connect at least one social account before planning content. Go to Settings → Social traffic.',
            ]);
        }

        $planPlatforms = $this->profileService->planPlatformsForUser($user);
        if ($planPlatforms === []) {
            throw ValidationException::withMessages([
                'plan' => 'No connected platforms selected for planning. Choose at least one platform below.',
            ]);
        }

        $existing = ContentEmployeePlan::query()
            ->where('user_id', $user->id)
            ->where('week_start', $weekStart->toDateString())
            ->when($campaign, fn ($q) => $q->where('campaign_id', $campaign->id))
            ->when(! $campaign, fn ($q) => $q->whereNull('campaign_id'))
            ->first();

        if ($existing && in_array($existing->status, [
            ContentEmployeePlan::STATUS_EXECUTING,
            ContentEmployeePlan::STATUS_COMPLETED,
        ], true)) {
            return $existing->load('items');
        }

        if ($existing) {
            $existing->items()->delete();
            $existing->delete();
        }

        $topicBundle = $this->topicSeedBundle($campaign, $funnel, $planningBrief);
        $topicSeeds = $topicBundle['seeds'];
        $autoSelect = $autoSelectFormats ?? $this->formatSelector->isAutoSelectEnabled($profile);

        if ($autoSelect) {
            $items = $this->buildAutoPlanItems($profile, $topicSeeds, $weekStart, $planPlatforms);
            $enabledFormats = array_values(array_unique(array_map(
                fn (array $item): string => (string) $item['format_key'],
                $items,
            )));
        } else {
            $enabledFormats = $this->formatsForManualPlan($profile, $planPlatforms);
            if ($enabledFormats === []) {
                throw ValidationException::withMessages([
                    'plan' => 'None of your enabled formats match your connected planning platforms. Update Traffic setup or change “Plan for platforms”.',
                ]);
            }
            $items = $this->buildPlanItems($profile, $enabledFormats, $topicSeeds, $weekStart, $planPlatforms);
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'plan' => 'Could not build a plan for your connected platforms. Try selecting more platforms or enabling more formats in Traffic setup.',
            ]);
        }

        $plan = ContentEmployeePlan::query()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign?->id,
            'funnel_id' => $funnel->id,
            'week_start' => $weekStart,
            'status' => ContentEmployeePlan::STATUS_DRAFT,
            'source' => $autoSelect ? 'ai_auto_formats' : 'ai_planner',
            'meta' => [
                'intensity' => $profile->intensity,
                'auto_select_formats' => $autoSelect,
                'format_count' => count($enabledFormats),
                'item_count' => count($items),
                'plan_platforms' => $planPlatforms,
                'connected_platforms' => $connected,
                'topic_source' => $topicBundle['source'],
                'topic_source_label' => $topicBundle['label'],
                'has_campaign_knowledge' => $topicBundle['has_knowledge'],
                'planning_brief' => $planningBrief !== '' ? Str::limit($planningBrief, 500, '') : null,
            ],
        ]);

        foreach ($items as $item) {
            ContentEmployeePlanItem::query()->create(array_merge($item, ['plan_id' => $plan->id]));
        }

        return $plan->fresh(['items']);
    }

    public function approve(ContentEmployeePlan $plan): ContentEmployeePlan
    {
        $plan->update(['status' => ContentEmployeePlan::STATUS_APPROVED]);

        return $plan->fresh(['items']);
    }

    public function execute(ContentEmployeePlan $plan, User $user): ContentEmployeePlan
    {
        if (! in_array($plan->status, [
            ContentEmployeePlan::STATUS_APPROVED,
            ContentEmployeePlan::STATUS_DRAFT,
        ], true)) {
            return $plan->load('items');
        }

        $plan->update(['status' => ContentEmployeePlan::STATUS_EXECUTING]);
        $funnel = Funnel::query()->findOrFail($plan->funnel_id);
        $funnel->loadMissing('campaign', 'settings');
        $cta = $this->ctaResolver->resolve($funnel->loadMissing('user', 'campaign'));
        $connected = $this->platformCatalog->connectedPlatformKeys((int) $user->id);
        $generationBase = $this->generationContextBase($funnel, $plan);

        if ($connected === []) {
            throw ValidationException::withMessages([
                'plan' => 'Connect at least one social account before creating posts.',
            ]);
        }

        $queued = 0;
        $skipped = 0;

        foreach ($plan->items as $item) {
            if ($item->promotion_post_id !== null) {
                continue;
            }

            $formatKey = $item->format_key;
            $spec = $this->formatCatalog->format($formatKey);
            if ($spec === null) {
                $item->update(['status' => ContentEmployeePlanItem::STATUS_FAILED]);
                $skipped++;

                continue;
            }

            $platform = $this->mapPlatformForPromotion((string) ($spec['platform'] ?? $item->platform));
            if (! in_array($platform, $connected, true)) {
                $item->update([
                    'status' => ContentEmployeePlanItem::STATUS_FAILED,
                    'metadata' => array_merge((array) ($item->metadata ?? []), [
                        'error' => 'No connected account for '.$platform,
                    ]),
                ]);
                $skipped++;

                continue;
            }

            $platforms = [$platform];
            $contentType = $this->formatCatalog->mapToPromotionContentType($formatKey);

            $post = FunnelPromotionPost::query()->create([
                'user_id' => $user->id,
                'funnel_id' => $funnel->id,
                'topic' => $item->topic,
                'content_type' => $contentType,
                'platforms' => $platforms,
                'publish_mode' => FunnelPromotionPost::MODE_APPROVE_FIRST,
                'status' => FunnelPromotionPost::STATUS_GENERATING,
                'cta_url' => $cta['url'],
                'cta_label' => $cta['label'],
                'scheduled_for' => $item->scheduled_for,
                'timezone' => (string) config('promotion.default_timezone', 'UTC'),
                'generation_context' => array_merge($generationBase, [
                    'content_format' => $formatKey,
                    'angle' => $item->angle,
                    'include_text' => true,
                    'include_image' => $contentType === FunnelPromotionPost::TYPE_IMAGE,
                    'source' => 'content_employee',
                    'conversion_goal' => 'Drive measurable growth — clicks, saves, and sign-ups. Specific beats generic.',
                ]),
                'metadata' => [
                    'format_key' => $formatKey,
                    'content_employee_plan_id' => $plan->id,
                    'content_employee_item_id' => $item->id,
                    'format_spec' => $spec,
                ],
            ]);

            $item->update([
                'promotion_post_id' => $post->id,
                'status' => ContentEmployeePlanItem::STATUS_GENERATING,
            ]);

            $this->generationDispatcher->dispatch(
                $post,
                $this->generationDispatcher->defaultTypesForPost($post),
            );
            $queued++;
        }

        $plan->update([
            'status' => ContentEmployeePlan::STATUS_COMPLETED,
            'meta' => array_merge((array) ($plan->meta ?? []), [
                'execute_summary' => [
                    'queued' => $queued,
                    'skipped' => $skipped,
                    'executed_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        return $plan->fresh(['items.promotionPost']);
    }

    /**
     * @return array<string, mixed>
     */
    public function planPayload(ContentEmployeePlan $plan): array
    {
        $plan->loadMissing(['items', 'campaign', 'funnel']);

        return [
            'id' => $plan->id,
            'uuid' => $plan->uuid,
            'week_start' => $plan->week_start?->toDateString(),
            'status' => $plan->status,
            'source' => $plan->source,
            'meta' => $plan->meta,
            'campaign' => $plan->campaign ? [
                'id' => $plan->campaign->id,
                'name' => $plan->campaign->name,
            ] : null,
            'funnel_id' => $plan->funnel_id,
            'items' => $plan->items->map(fn (ContentEmployeePlanItem $item): array => [
                'id' => $item->id,
                'format_key' => $item->format_key,
                'platform' => $item->platform,
                'topic' => $item->topic,
                'angle' => $item->angle,
                'scheduled_for' => $item->scheduled_for?->toIso8601String(),
                'status' => $item->status,
                'promotion_post_id' => $item->promotion_post_id,
                'format_label' => $this->formatCatalog->format($item->format_key)['label'] ?? $item->format_key,
            ])->values()->all(),
        ];
    }

    protected function resolveFunnel(User $user, ?Campaign $campaign): Funnel
    {
        if ($campaign) {
            return $this->trafficHub->ensureTrafficFunnel($campaign);
        }

        return $this->workspace->funnelForUser($user);
    }

    /**
     * @return array{seeds: list<string>, source: string, label: string, has_knowledge: bool}
     */
    protected function topicSeedBundle(?Campaign $campaign, Funnel $funnel, ?string $planningBrief = null): array
    {
        $brief = trim((string) $planningBrief);

        if ($campaign !== null) {
            $kbSeeds = $this->knowledge->promotionTopicSeeds($campaign);
            if ($kbSeeds !== []) {
                $seeds = $kbSeeds;
                if ($brief !== '') {
                    $seeds = array_values(array_unique(array_merge(
                        $this->topicsFromPlanningBrief($brief),
                        $seeds,
                    )));
                }

                return [
                    'seeds' => $seeds !== [] ? $seeds : $kbSeeds,
                    'source' => 'knowledge_base',
                    'label' => $brief !== '' ? 'Campaign KB + your guidance' : 'Campaign knowledge base',
                    'has_knowledge' => true,
                ];
            }

            if ($brief !== '') {
                $fromBrief = $this->standaloneTopicSeeds($funnel, $brief);

                return [
                    ...$fromBrief,
                    'label' => 'Your brief + campaign offer',
                ];
            }

            try {
                $suggested = $this->topicSuggestions->generate($funnel, 20);
                $topics = array_values(array_filter(array_map(
                    fn (array $row): string => trim((string) ($row['topic'] ?? '')),
                    $suggested,
                )));

                if ($topics !== []) {
                    return [
                        'seeds' => $topics,
                        'source' => 'ai_suggested',
                        'label' => 'AI topic suggestions (no KB yet)',
                        'has_knowledge' => false,
                    ];
                }
            } catch (\Throwable) {
                // fall through to product-based seeds
            }

            $product = (string) ($campaign->offer_data['product_name'] ?? $campaign->name);

            return [
                'seeds' => [
                    "The #1 mistake people make with {$product}",
                    "Why {$product} beats DIY alternatives (with proof)",
                    "3 signs you need {$product} this week",
                    "Objection I hear about {$product} — answered",
                    "Quick win you get from {$product} in 24 hours",
                ],
                'source' => 'product_fallback',
                'label' => 'Product name templates (no KB yet)',
                'has_knowledge' => false,
            ];
        }

        return $this->standaloneTopicSeeds($funnel, $brief);
    }

    /**
     * @return array{seeds: list<string>, source: string, label: string, has_knowledge: bool}
     */
    protected function standaloneTopicSeeds(Funnel $funnel, string $planningBrief): array
    {
        $brief = trim($planningBrief);

        if ($brief === '') {
            return [
                'seeds' => [
                    'The mistake costing you clicks every day',
                    'One shift that doubled my conversion rate',
                    'Proof this approach works (real numbers)',
                    'Why most people fail at traffic — and the fix',
                    'The hook that stops the scroll',
                    'Objection handling that builds trust fast',
                    'Before vs after: what changed',
                    'Save this — checklist for your next post',
                ],
                'source' => 'standalone_generic',
                'label' => 'Generic traffic angles',
                'has_knowledge' => false,
            ];
        }

        $explicit = $this->topicsFromPlanningBrief($brief);
        if (count($explicit) >= 3) {
            return [
                'seeds' => $explicit,
                'source' => 'user_brief',
                'label' => 'Topics from your brief',
                'has_knowledge' => true,
            ];
        }

        try {
            $suggested = $this->topicSuggestions->generate($funnel, 20, $brief);
            $topics = array_values(array_filter(array_map(
                fn (array $row): string => trim((string) ($row['topic'] ?? '')),
                $suggested,
            )));

            if ($topics !== []) {
                return [
                    'seeds' => $topics,
                    'source' => 'ai_from_brief',
                    'label' => 'AI topics from your brief',
                    'has_knowledge' => true,
                ];
            }
        } catch (\Throwable) {
            // fall through
        }

        $fallback = array_values(array_unique(array_merge(
            $explicit,
            $this->sentenceSeedsFromBrief($brief),
        )));

        if ($fallback !== []) {
            return [
                'seeds' => $fallback,
                'source' => 'brief_parsed',
                'label' => 'Parsed from your brief',
                'has_knowledge' => true,
            ];
        }

        return [
            'seeds' => [Str::limit($brief, 200, '')],
            'source' => 'brief_single',
            'label' => 'Your content brief',
            'has_knowledge' => true,
        ];
    }

    /**
     * @return list<string>
     */
    protected function topicsFromPlanningBrief(string $brief): array
    {
        $topics = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($brief)) ?: [] as $line) {
            $line = trim((string) preg_replace('/^[-*•\d]+[\.)]\s*/', '', trim($line)));
            if ($line === '' || mb_strlen($line) < 12) {
                continue;
            }

            $topics[] = Str::limit($line, 250, '');
        }

        return array_values(array_slice(array_unique($topics), 0, 24));
    }

    /**
     * @return list<string>
     */
    protected function sentenceSeedsFromBrief(string $brief): array
    {
        $chunks = preg_split('/(?<=[.!?])\s+/', preg_replace('/\s+/', ' ', trim($brief))) ?: [];
        $seeds = [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if (mb_strlen($chunk) >= 20 && mb_strlen($chunk) <= 220) {
                $seeds[] = Str::limit($chunk, 250, '');
            }
        }

        return array_values(array_slice(array_unique($seeds), 0, 12));
    }

    protected function applyPlanningBriefToStandaloneFunnel(Funnel $funnel, string $brief): void
    {
        $funnel->loadMissing('settings');
        $settings = $funnel->settings;
        if ($settings === null) {
            return;
        }

        $settings->update([
            'webinar_description' => Str::limit(trim($brief), 2000, ''),
            'traffic_ai_extra_context' => Str::limit(trim($brief), 2000, ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function generationContextBase(Funnel $funnel, ContentEmployeePlan $plan): array
    {
        $meta = is_array($plan->meta) ? $plan->meta : [];
        $ctx = $this->funnelContextBuilder->build($funnel);

        $brief = [
            'product' => $ctx['product_name'] ?? null,
            'audience' => $ctx['audience'] ?? null,
            'value_proposition' => $ctx['optin_intro'] ?? null,
        ];

        if (! empty($meta['planning_brief'])) {
            $brief['user_brief'] = $meta['planning_brief'];
        }

        $pass1 = is_array($ctx['knowledge_pass1'] ?? null) ? $ctx['knowledge_pass1'] : [];
        if ($pass1 !== []) {
            $brief['unique_mechanism'] = $pass1['unique_mechanism'] ?? null;
            $brief['pain_points'] = array_values(array_slice((array) ($pass1['core_pain_points'] ?? []), 0, 3));
        }

        return array_filter([
            'topic_source' => $meta['topic_source'] ?? null,
            'knowledge_brief' => array_filter($brief, fn ($v) => $v !== null && $v !== [] && $v !== ''),
            'context' => $meta['planning_brief'] ?? $ctx['optin_intro'] ?? $ctx['webinar_description'] ?? null,
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * @param  list<string>  $planPlatforms
     * @return list<string>
     */
    protected function formatsForManualPlan(UserTrafficProfile $profile, array $planPlatforms): array
    {
        $enabled = $profile->enabled_formats ?? $this->formatCatalog->allFormatKeys();

        return array_values(array_filter($enabled, function (string $formatKey) use ($planPlatforms): bool {
            $spec = $this->formatCatalog->format($formatKey);
            if ($spec === null) {
                return false;
            }

            $platform = $this->mapPlatformForPromotion((string) ($spec['platform'] ?? ''));

            return in_array($platform, $planPlatforms, true);
        }));
    }

    /**
     * @param  list<string>  $enabledFormats
     * @param  list<string>  $topicSeeds
     * @param  list<string>  $planPlatforms
     * @return list<array<string, mixed>>
     */
    protected function buildPlanItems(
        UserTrafficProfile $profile,
        array $enabledFormats,
        array $topicSeeds,
        Carbon $weekStart,
        array $planPlatforms,
    ): array {
        $items = [];
        $seedIndex = 0;
        $slotHour = 10;

        foreach ($enabledFormats as $formatKey) {
            $spec = $this->formatCatalog->format($formatKey);
            if ($spec === null) {
                continue;
            }

            $platform = (string) ($spec['platform'] ?? '');
            if (! in_array($this->mapPlatformForPromotion($platform), $planPlatforms, true)) {
                continue;
            }

            $count = (int) max(1, round($this->profileService->frequencyForFormat($profile, $formatKey)));

            for ($i = 0; $i < $count; $i++) {
                $dayOffset = ($seedIndex + $i) % 7;
                $scheduled = $weekStart->copy()->addDays($dayOffset)->setTime($slotHour + ($i % 4), ($i * 15) % 60);
                $topic = $topicSeeds[($seedIndex + $i) % count($topicSeeds)];
                $angle = $this->angleForFormat($formatKey, $topic);

                $items[] = [
                    'format_key' => $formatKey,
                    'platform' => $platform,
                    'topic' => Str::limit($topic, 250, ''),
                    'angle' => Str::limit($angle, 250, ''),
                    'scheduled_for' => $scheduled,
                    'status' => ContentEmployeePlanItem::STATUS_PLANNED,
                    'format_spec' => $spec,
                    'metadata' => ['slot' => $i + 1],
                ];
            }

            $seedIndex += $count;
        }

        usort($items, fn (array $a, array $b): int => ($a['scheduled_for'] ?? now()) <=> ($b['scheduled_for'] ?? now()));

        return $items;
    }

    /**
     * Auto mode — pick best format per topic from full catalog.
     *
     * @param  list<string>  $topicSeeds
     * @param  list<string>  $planPlatforms
     * @return list<array<string, mixed>>
     */
    protected function buildAutoPlanItems(
        UserTrafficProfile $profile,
        array $topicSeeds,
        Carbon $weekStart,
        array $planPlatforms,
    ): array {
        $topics = $topicSeeds !== [] ? $topicSeeds : ['Affiliate marketing tips'];
        $target = $this->formatSelector->weeklyPostTarget((string) $profile->intensity);
        $items = [];
        $usedFormats = [];
        $slotHour = 9;

        for ($i = 0; $i < $target; $i++) {
            $topic = (string) $topics[$i % count($topics)];
            $formatKey = $this->formatSelector->pickBestFormat(
                $topic,
                $profile,
                $planPlatforms,
                $usedFormats,
                $planPlatforms,
            );
            $usedFormats[] = $formatKey;

            $spec = $this->formatCatalog->format($formatKey);
            if ($spec === null) {
                continue;
            }

            $platform = (string) ($spec['platform'] ?? '');
            $dayOffset = $i % 7;
            $scheduled = $weekStart->copy()->addDays($dayOffset)->setTime($slotHour + ($i % 5), ($i * 12) % 60);

            $items[] = [
                'format_key' => $formatKey,
                'platform' => $platform,
                'topic' => Str::limit($topic, 250, ''),
                'angle' => Str::limit($this->angleForFormat($formatKey, $topic), 250, ''),
                'scheduled_for' => $scheduled,
                'status' => ContentEmployeePlanItem::STATUS_PLANNED,
                'format_spec' => $spec,
                'metadata' => [
                    'slot' => $i + 1,
                    'auto_selected' => true,
                ],
            ];
        }

        usort($items, fn (array $a, array $b): int => ($a['scheduled_for'] ?? now()) <=> ($b['scheduled_for'] ?? now()));

        return $items;
    }

    protected function angleForFormat(string $formatKey, string $topic): string
    {
        $spec = $this->formatCatalog->format($formatKey);
        $label = (string) ($spec['label'] ?? $formatKey);
        $generator = (string) ($spec['generator'] ?? 'text');

        return match ($generator) {
            'carousel' => "{$label}: swipeable proof + CTA — {$topic}",
            'thread' => "{$label}: story arc → objection → CTA — {$topic}",
            'reel_script' => "{$label}: hook in 2s, payoff fast — {$topic}",
            'poll' => "{$label}: engagement that surfaces buying intent — {$topic}",
            'reddit_discussion' => "Value-first discussion (no pitch) — {$topic}",
            'pin' => "{$label}: SEO title + save bait — {$topic}",
            default => "{$label}: conversion-focused — {$topic}",
        };
    }

    protected function mapPlatformForPromotion(string $platform): string
    {
        return match ($platform) {
            'twitter' => 'twitter',
            default => $platform,
        };
    }
}
