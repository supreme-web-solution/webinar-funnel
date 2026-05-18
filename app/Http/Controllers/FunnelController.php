<?php

namespace App\Http\Controllers;

use App\Http\Requests\FunnelPageUpdateRequest;
use App\Http\Requests\FunnelSettingsUpdateRequest;
use App\Http\Requests\FunnelStoreRequest;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Funnel;
use App\Models\FunnelAiSource;
use App\Models\FunnelAiSourceChunk;
use App\Models\FunnelPage;
use App\Models\FunnelVideoViewStat;
use App\Models\IntegrationAccount;
use App\Models\Keyword;
use App\Models\Mention;
use App\Models\SocialAccount;
use App\Models\Template;
use App\Services\Funnels\PageSanitizer;
use App\Services\Funnels\PublicFunnelResolver;
use App\Services\Mentions\KeywordMentionCapEnforcer;
use App\Services\TrafficAi\TrafficSocialAccountResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FunnelController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();
        $username = auth()->user()->username ?? 'user-'.$userId;

        $funnels = Funnel::query()
            ->where('user_id', $userId)
            ->with('template:id,name,category')
            ->withCount('leads')
            ->latest()
            ->get(['id', 'template_id', 'name', 'slug', 'status', 'published_at', 'created_at']);

        $funnels = $funnels->map(function (Funnel $funnel) use ($username) {
            return [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'slug' => $funnel->slug,
                'status' => $funnel->status,
                'published_at' => $funnel->published_at,
                'created_at' => $funnel->created_at,
                'leads_count' => $funnel->leads_count,
                'template' => $funnel->template
                    ? [
                        'name' => $funnel->template->name,
                        'category' => $funnel->template->category,
                    ]
                    : null,
                'public_url' => $funnel->status === 'published'
                    ? route('public.optin', [
                        'username' => $username,
                        'slug' => $funnel->slug,
                    ])
                    : null,
            ];
        })->values();

        $stats = [
            'total' => $funnels->count(),
            'published' => $funnels->where('status', 'published')->count(),
            'draft' => $funnels->where('status', 'draft')->count(),
            'archived' => $funnels->where('status', 'archived')->count(),
        ];

        return Inertia::render('funnels/Index', [
            'funnels' => $funnels,
            'stats' => $stats,
        ]);
    }

    public function create(): Response
    {
        $templates = Template::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->limit(50)
            ->get(['id', 'name', 'slug', 'category', 'conversion_style', 'thumbnail_url']);

        return Inertia::render('funnels/Create', [
            'templates' => $templates,
        ]);
    }

    public function store(FunnelStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $template = Template::query()->findOrFail($validated['template_id']);
        $currentVersion = $template->versions()->where('is_current', true)->firstOrFail();
        $isScratch = (bool) ($validated['is_scratch'] ?? false);

        $optinSchema = $isScratch
            ? $this->buildScratchOptinSchema((array) $currentVersion->optin_schema)
            : (array) $currentVersion->optin_schema;
        $webinarSchema = $isScratch
            ? $this->buildScratchWebinarSchema((array) $currentVersion->webinar_schema)
            : (array) $currentVersion->webinar_schema;
        $defaultSettings = $isScratch
            ? $this->buildScratchSettings((array) ($currentVersion->default_settings ?? []))
            : ($currentVersion->default_settings ?? [
                'chat_mode' => 'simulated',
                'allow_replay' => true,
            ]);

        $funnel = Funnel::query()->create([
            'user_id' => $request->user()->id,
            'template_id' => $template->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug']),
            'status' => 'draft',
            'meta' => ['template_version' => $currentVersion->version],
        ]);

        FunnelPage::query()->create([
            'funnel_id' => $funnel->id,
            'page_type' => 'optin',
            'schema' => $optinSchema,
            'version' => 1,
        ]);

        FunnelPage::query()->create([
            'funnel_id' => $funnel->id,
            'page_type' => 'webinar',
            'schema' => $webinarSchema,
            'version' => 1,
        ]);

        $funnel->settings()->create($defaultSettings);

        ChatRoom::query()->create([
            'funnel_id' => $funnel->id,
            'mode' => 'simulated',
            'is_active' => true,
        ]);

        return to_route('funnels.edit', $funnel->id);
    }

    public function edit(Request $request, Funnel $funnel): Response
    {
        $this->authorizeFunnel($funnel);

        $funnel->load(['template', 'pages', 'settings', 'chatRoom', 'integrations.integrationAccount']);
        $integrationAccounts = IntegrationAccount::query()
            ->where('user_id', auth()->id())
            ->get(['id', 'name', 'provider']);
        $username = $funnel->user->username ?? 'user-'.$funnel->user_id;
        $conversationSummaries = $this->buildConversationSummaries($funnel, 50);
        $trafficData = $this->buildTrafficData($request, $funnel);
        $videoStats = $this->buildVideoStatsData($funnel);

        return Inertia::render('funnels/Edit', [
            'funnel' => $funnel,
            'integrationAccounts' => $integrationAccounts,
            'conversationSummaries' => $conversationSummaries,
            'traffic' => $trafficData,
            'videoStats' => $videoStats,
            'aiSources' => FunnelAiSource::query()
                ->where('funnel_id', $funnel->id)
                ->latest()
                ->limit(8)
                ->get()
                ->map(function (FunnelAiSource $source) use ($funnel): array {
                    return [
                        'id' => $source->id,
                        'type' => $source->type,
                        'title' => $source->title,
                        'source_url' => $source->source_url,
                        'status' => $source->status,
                        'error_message' => $source->error_message,
                        'processed_at' => $source->processed_at,
                        'chunk_count' => (int) FunnelAiSourceChunk::query()
                            ->where('funnel_ai_source_id', $source->id)
                            ->count(),
                        'chunks_url' => route('funnels.ai.sources.chunks', [$funnel->id, $source->id]),
                        'delete_url' => route('funnels.ai.sources.delete', [$funnel->id, $source->id]),
                    ];
                })
                ->values(),
            'aiSourceUrls' => [
                'index' => route('funnels.ai.sources.index', $funnel->id),
                'url' => route('funnels.ai.sources.store-url', $funnel->id),
                'transcript' => route('funnels.ai.sources.store-transcript', $funnel->id),
                'file' => route('funnels.ai.sources.store-file', $funnel->id),
                'bulk_delete' => route('funnels.ai.sources.bulk-delete', $funnel->id),
            ],
            'publicLinks' => [
                'optin' => route('public.optin', [
                    'username' => $username,
                    'slug' => $funnel->slug,
                ]),
                'webinar' => route('public.webinar', [
                    'username' => $username,
                    'slug' => $funnel->slug,
                ]),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTrafficData(Request $request, Funnel $funnel): array
    {
        $mentionCap = KeywordMentionCapEnforcer::maxMentionsPerKeyword();
        $capEnforcer = app(KeywordMentionCapEnforcer::class);

        $keywords = Keyword::query()
            ->where('user_id', $funnel->user_id)
            ->where('funnel_id', $funnel->id)
            ->withCount('mentions')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Keyword $keyword) use ($mentionCap, $capEnforcer) {
                $mentionsCount = (int) $keyword->mentions_count;
                $capReached = $mentionsCount >= $mentionCap;

                if ($capReached) {
                    $capEnforcer->enforceCap($keyword);
                }

                return [
                    'id' => $keyword->id,
                    'name' => $keyword->name,
                    'is_active' => $capReached ? false : (bool) $keyword->is_active,
                    'email_notifications' => (bool) $keyword->email_notifications,
                    'platforms' => $keyword->platforms ?? [],
                    'mentions_count' => $mentionsCount,
                    'mention_cap_reached' => $capReached,
                ];
            })
            ->values();

        $mentionsQuery = Mention::query()
            ->where('user_id', $funnel->user_id)
            ->whereHas('keyword', fn ($q) => $q->where('funnel_id', $funnel->id))
            ->with('keyword:id,name,funnel_id');

        $trafficSearch = trim((string) $request->query('traffic_search', ''));
        $trafficPlatform = (string) $request->query('traffic_platform', '');
        $trafficKeywordId = $request->query('traffic_keyword_id');

        if ($trafficPlatform !== '') {
            $mentionsQuery->where('source_type', $trafficPlatform);
        }

        if ($trafficKeywordId) {
            $mentionsQuery->where('keyword_id', $trafficKeywordId);
        }

        if ($trafficSearch !== '') {
            $mentionsQuery->where(function ($q) use ($trafficSearch): void {
                $q->where('title', 'like', "%{$trafficSearch}%")
                    ->orWhere('content', 'like', "%{$trafficSearch}%")
                    ->orWhere('username', 'like', "%{$trafficSearch}%");
            });
        }

        $mentions = $mentionsQuery->orderByDesc('posted_at')->paginate(10)->withQueryString();

        $platformCounts = Mention::query()
            ->where('user_id', $funnel->user_id)
            ->whereHas('keyword', fn ($q) => $q->where('funnel_id', $funnel->id))
            ->selectRaw('source_type, count(*) as cnt')
            ->groupBy('source_type')
            ->pluck('cnt', 'source_type');

        return [
            'keywords' => $keywords,
            'mentions' => $mentions,
            'stats' => [
                'total' => Mention::query()
                    ->where('user_id', $funnel->user_id)
                    ->whereHas('keyword', fn ($q) => $q->where('funnel_id', $funnel->id))
                    ->count(),
                'this_week' => Mention::query()
                    ->where('user_id', $funnel->user_id)
                    ->whereHas('keyword', fn ($q) => $q->where('funnel_id', $funnel->id))
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->count(),
                'keywords_count' => $keywords->count(),
                'platforms' => $platformCounts,
            ],
            'filters' => [
                'search' => $trafficSearch,
                'platform' => $trafficPlatform,
                'keyword_id' => $trafficKeywordId,
            ],
            'social_accounts' => SocialAccount::query()
                ->where('user_id', $funnel->user_id)
                ->orderBy('platform')
                ->get(['id', 'platform', 'platform_username', 'posts_today', 'posts_today_reset_on']),
            'max_replies_per_day_per_account' => (int) config('traffic_ai.max_replies_per_day_per_account', 20),
            'limits' => [
                'max_keywords_per_funnel' => KeywordMentionCapEnforcer::maxKeywordsPerFunnel(),
                'max_mentions_per_keyword' => $mentionCap,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVideoStatsData(Funnel $funnel): array
    {
        $baseQuery = FunnelVideoViewStat::query()->where('funnel_id', $funnel->id);

        return [
            'accessed' => (clone $baseQuery)->count(),
            'watched_60s' => (clone $baseQuery)->where('reached_60s', true)->count(),
            'watched_50_percent' => (clone $baseQuery)->where('reached_50_percent', true)->count(),
            'watched_to_end' => (clone $baseQuery)->where('reached_100_percent', true)->count(),
            'avg_watch_seconds' => (int) round((float) ((clone $baseQuery)->avg('watched_seconds') ?? 0)),
        ];
    }

    public function updatePage(
        FunnelPageUpdateRequest $request,
        Funnel $funnel,
        PageSanitizer $sanitizer,
        PublicFunnelResolver $resolver
    ): RedirectResponse {
        $this->authorizeFunnel($funnel);
        $validated = $request->validated();

        $page = $funnel->pages()->firstOrNew([
            'page_type' => $validated['page_type'],
        ]);

        $page->fill([
            'schema' => $sanitizer->sanitize((array) ($validated['schema'] ?? $request->input('schema', []))),
            'version' => ($page->version ?? 0) + 1,
        ])->save();

        if ($funnel->status === 'published') {
            $funnel->loadMissing('user');
            $resolver->forget($funnel->user->username ?? 'user-'.$funnel->user_id, $funnel->slug);
        }

        return back()->with('success', 'Page updated.');
    }

    public function updateSettings(
        FunnelSettingsUpdateRequest $request,
        Funnel $funnel,
        PublicFunnelResolver $resolver
    ): RedirectResponse {
        $this->authorizeFunnel($funnel);
        $validated = $request->validated();
        $integrationIds = $validated['integration_account_ids'] ?? [];
        $incomingIntegrationConfigs = $validated['integration_configs'] ?? [];
        unset($validated['integration_account_ids']);
        unset($validated['integration_configs']);

        $settings = $funnel->settings()->firstOrNew(['funnel_id' => $funnel->id]);

        if (array_key_exists('traffic_ai_social_account_ids', $validated)) {
            $validated['traffic_ai_social_account_ids'] = app(TrafficSocialAccountResolver::class)
                ->normalizeMapForUser((int) $funnel->user_id, $validated['traffic_ai_social_account_ids']);
        }

        $settings->fill($validated)->save();

        $funnel->chatRoom()->updateOrCreate(
            ['funnel_id' => $funnel->id],
            ['mode' => $validated['chat_mode'] ?? 'simulated']
        );

        if (! empty($validated['chat_seed_messages'])) {
            $chatRoom = $funnel->chatRoom()->first();
            if ($chatRoom) {
                $chatRoom->messages()->delete();

                foreach ($validated['chat_seed_messages'] as $message) {
                    $chatRoom->messages()->create([
                        'author_name' => $message['author'],
                        'message' => $message['message'],
                        'is_seeded' => true,
                        'published_at' => $message['published_at'] ?? now(),
                    ]);
                }
            }
        }

        $existingConfigs = $funnel->integrations()
            ->get(['integration_account_id', 'provider_list_config'])
            ->mapWithKeys(fn ($integration) => [
                (string) $integration->integration_account_id => is_array($integration->provider_list_config)
                    ? $integration->provider_list_config
                    : [],
            ]);

        $funnel->integrations()->delete();

        foreach ($integrationIds as $integrationId) {
            $providerConfig = $existingConfigs->get((string) $integrationId, []);

            if (isset($incomingIntegrationConfigs[$integrationId]) && is_array($incomingIntegrationConfigs[$integrationId])) {
                $providerConfig = $incomingIntegrationConfigs[$integrationId];
            }

            $funnel->integrations()->create([
                'integration_account_id' => $integrationId,
                'provider_list_config' => $providerConfig,
                'enabled' => true,
            ]);
        }

        if ($funnel->status === 'published') {
            $funnel->loadMissing('user');
            $resolver->forget($funnel->user->username ?? 'user-'.$funnel->user_id, $funnel->slug);
        }

        return back()->with('success', 'Settings updated.');
    }

    public function publish(Funnel $funnel, PublicFunnelResolver $resolver): RedirectResponse
    {
        $this->authorizeFunnel($funnel);

        $funnel->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        $funnel->pages()->update(['published_at' => now()]);
        $funnel->loadMissing('user');

        $username = $funnel->user->username ?? 'user-'.$funnel->user_id;
        $resolver->forget($username, $funnel->slug);

        return back()->with('success', 'Funnel published successfully.');
    }

    public function unpublish(Funnel $funnel, PublicFunnelResolver $resolver): RedirectResponse
    {
        $this->authorizeFunnel($funnel);

        $funnel->update([
            'status' => 'draft',
            'published_at' => null,
        ]);

        $funnel->pages()->update(['published_at' => null]);
        $funnel->loadMissing('user');

        $username = $funnel->user->username ?? 'user-'.$funnel->user_id;
        $resolver->forget($username, $funnel->slug);

        return back()->with('success', 'Funnel unpublished and moved back to draft.');
    }

    public function archive(Funnel $funnel, PublicFunnelResolver $resolver): RedirectResponse
    {
        $this->authorizeFunnel($funnel);

        $funnel->update([
            'status' => 'archived',
            'published_at' => null,
        ]);

        $funnel->pages()->update(['published_at' => null]);
        $funnel->loadMissing('user');

        $username = $funnel->user->username ?? 'user-'.$funnel->user_id;
        $resolver->forget($username, $funnel->slug);

        return back()->with('success', 'Funnel archived.');
    }

    public function destroy(Funnel $funnel, PublicFunnelResolver $resolver): RedirectResponse
    {
        $this->authorizeFunnel($funnel);
        $funnel->loadMissing('user');

        $username = $funnel->user->username ?? 'user-'.$funnel->user_id;
        $slug = $funnel->slug;

        $funnel->delete();
        $resolver->forget($username, $slug);

        return to_route('funnels.index')->with('success', 'Funnel deleted.');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildConversationSummaries(Funnel $funnel, int $limit = 50)
    {
        $chatRoomId = $funnel->chatRoom?->id;
        if (! $chatRoomId) {
            return collect();
        }

        $rows = ChatMessage::query()
            ->where('chat_room_id', $chatRoomId)
            ->whereNotNull('conversation_key')
            ->selectRaw('conversation_key, MAX(id) as latest_id, COUNT(*) as message_count')
            ->groupBy('conversation_key')
            ->orderByDesc('latest_id')
            ->limit($limit)
            ->get();

        $latestMessages = ChatMessage::query()
            ->whereIn('id', $rows->pluck('latest_id')->all())
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($latestMessages) {
            $latest = $latestMessages->get((int) $row->latest_id);

            return [
                'conversation_key' => $row->conversation_key,
                'attendee_name' => $latest?->attendee_name ?? 'Anonymous attendee',
                'attendee_email' => $latest?->attendee_email,
                'latest_message' => $latest?->message,
                'message_count' => (int) $row->message_count,
            ];
        })->values();
    }

    private function authorizeFunnel(Funnel $funnel): void
    {
        abort_unless((int) auth()->id() === (int) $funnel->user_id, 403);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function buildScratchOptinSchema(array $schema): array
    {
        $schema['html'] = '';
        $schema['css'] = '';
        $schema['hero'] = [
            'headline' => '',
            'subheadline' => '',
            'cta' => '',
        ];
        $schema['what_youll_discover'] = [];

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function buildScratchWebinarSchema(array $schema): array
    {
        $schema['title'] = '';
        $schema['description'] = '';
        $schema['video'] = ['provider' => 'youtube', 'url' => ''];

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function buildScratchSettings(array $settings): array
    {
        $settings['webinar_title'] = '';
        $settings['webinar_description'] = '';
        $settings['video_url'] = '';
        $settings['webinar_duration_seconds'] = null;
        $settings['webinar_cta_label'] = '';
        $settings['webinar_cta_url'] = '';
        $settings['affiliate_request_link'] = '';
        $settings['jv_page'] = '';
        $settings['chat_seed_messages'] = [];
        $settings['offers'] = [];
        $settings['exit_popup_enabled'] = false;
        $settings['exit_popup_show_close'] = true;
        $settings['exit_popup_title'] = '';
        $settings['exit_popup_description'] = '';
        $settings['exit_popup_cta_label'] = '';
        $settings['exit_popup_cta_url'] = '';
        $settings['redirect_enabled'] = false;
        $settings['redirect_url'] = '';
        $settings['webinar_ai_enabled'] = false;
        $settings['webinar_ai_auto_reply_enabled'] = true;
        $settings['webinar_ai_assistant_name'] = '';
        $settings['chat_mode'] = $settings['chat_mode'] ?? 'simulated';
        $settings['allow_replay'] = array_key_exists('allow_replay', $settings) ? (bool) $settings['allow_replay'] : true;
        $settings['branding'] = $settings['branding'] ?? ['primary' => '#40E0D0', 'secondary' => '#FFAD00'];

        return $settings;
    }
}
