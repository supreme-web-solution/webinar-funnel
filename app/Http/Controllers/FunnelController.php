<?php

namespace App\Http\Controllers;

use App\Http\Requests\FunnelPageUpdateRequest;
use App\Http\Requests\FunnelSettingsUpdateRequest;
use App\Http\Requests\FunnelStoreRequest;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Funnel;
use App\Models\FunnelPage;
use App\Models\IntegrationAccount;
use App\Models\Template;
use App\Services\Funnels\PageSanitizer;
use App\Services\Funnels\PublicFunnelResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FunnelController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();

        $funnels = Funnel::query()
            ->where('user_id', $userId)
            ->with('template:id,name,category')
            ->withCount('leads')
            ->latest()
            ->get(['id', 'template_id', 'name', 'slug', 'status', 'published_at', 'created_at']);

        $stats = [
            'total'     => $funnels->count(),
            'published' => $funnels->where('status', 'published')->count(),
            'draft'     => $funnels->where('status', 'draft')->count(),
        ];

        return Inertia::render('funnels/Index', [
            'funnels' => $funnels,
            'stats'   => $stats,
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
            'schema' => $currentVersion->optin_schema,
            'version' => 1,
        ]);

        FunnelPage::query()->create([
            'funnel_id' => $funnel->id,
            'page_type' => 'webinar',
            'schema' => $currentVersion->webinar_schema,
            'version' => 1,
        ]);

        $funnel->settings()->create($currentVersion->default_settings ?? [
            'chat_mode' => 'simulated',
            'allow_replay' => true,
            'double_opt_in' => false,
        ]);

        ChatRoom::query()->create([
            'funnel_id' => $funnel->id,
            'mode' => 'simulated',
            'is_active' => true,
        ]);

        return to_route('funnels.edit', $funnel->id);
    }

    public function edit(Funnel $funnel): Response
    {
        $this->authorizeFunnel($funnel);

        $funnel->load(['template', 'pages', 'settings', 'integrations.integrationAccount']);
        $integrationAccounts = IntegrationAccount::query()
            ->where('user_id', auth()->id())
            ->get(['id', 'name', 'provider']);
        $username = $funnel->user->username ?? 'user-'.$funnel->user_id;
        $conversationSummaries = $funnel->chatRoom?->messages()
            ->selectRaw('conversation_key, MAX(id) as latest_id')
            ->groupBy('conversation_key')
            ->orderByDesc('latest_id')
            ->limit(50)
            ->get()
            ->map(function ($row) use ($funnel) {
                $latest = ChatMessage::query()->whereKey((int) $row->latest_id)->first();
                $count = $funnel->chatRoom?->messages()
                    ->where('conversation_key', $row->conversation_key)
                    ->count() ?? 0;

                return [
                    'conversation_key' => $row->conversation_key,
                    'attendee_name' => $latest?->attendee_name ?? 'Anonymous attendee',
                    'attendee_email' => $latest?->attendee_email,
                    'latest_message' => $latest?->message,
                    'message_count' => $count,
                ];
            })
            ->values() ?? collect();

        return Inertia::render('funnels/Edit', [
            'funnel' => $funnel,
            'integrationAccounts' => $integrationAccounts,
            'conversationSummaries' => $conversationSummaries,
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
        unset($validated['integration_account_ids']);

        $settings = $funnel->settings()->firstOrNew();
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

        $funnel->integrations()->delete();

        foreach ($integrationIds as $integrationId) {
            $funnel->integrations()->create([
                'integration_account_id' => $integrationId,
                'provider_list_config' => [],
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

    private function authorizeFunnel(Funnel $funnel): void
    {
        abort_unless((int) auth()->id() === (int) $funnel->user_id, 403);
    }
}
