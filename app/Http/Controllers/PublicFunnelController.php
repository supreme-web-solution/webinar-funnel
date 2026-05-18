<?php

namespace App\Http\Controllers;

use App\Models\FunnelVideoViewStat;
use App\Services\Funnels\FunnelPageViewRecorder;
use App\Services\Funnels\PublicFunnelResolver;
use App\Services\Funnels\WebinarPublicChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicFunnelController extends Controller
{
    private const GRAPES_GOOGLE_FONTS_URL =
        'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900'.
        '&family=Roboto:wght@400;700&family=Open+Sans:wght@400;600;700'.
        '&family=Lato:wght@400;700&family=Montserrat:wght@400;600;700;900'.
        '&family=Poppins:wght@400;600;700;900&family=Raleway:wght@400;600;700'.
        '&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,700'.
        '&family=Plus+Jakarta+Sans:wght@400;600;700&family=Outfit:wght@400;600;700;900'.
        '&family=Nunito:wght@400;600;700&family=Oswald:wght@400;500;600;700'.
        '&family=Source+Sans+3:wght@400;600;700'.
        '&family=Playfair+Display:ital,wght@0,400;0,700;1,400'.
        '&family=Merriweather:ital,wght@0,400;0,700;1,400'.
        '&family=Lora:ital,wght@0,400;0,700;1,400&display=swap';

    public function __construct(
        private WebinarPublicChatService $webinarPublicChat,
        private FunnelPageViewRecorder $pageViewRecorder,
    ) {}

    public function optin(
        Request $request,
        string $username,
        string $slug,
        PublicFunnelResolver $resolver
    ): Response|HttpResponse {
        $funnel = $resolver->resolve($username, $slug);

        if (! $funnel) {
            abort(404);
        }

        $this->pageViewRecorder->record($funnel, 'optin', $request);

        $optinPage = $funnel->pages->firstWhere('page_type', 'optin');
        $schema = $optinPage?->schema ?? [];
        $pageHtml = $schema['html'] ?? null;
        $pageCss = $schema['css'] ?? null;

        $response = Inertia::render('public/Optin', [
            'funnel' => [
                'name' => $funnel->name,
                'slug' => $funnel->slug,
                'owner' => $username,
            ],
            'pageHtml' => $pageHtml,
            'pageCss' => $pageCss,
            // Legacy fallback keys kept for old funnel schemas
            'page' => $schema,
        ]);

        if (! filled($pageHtml)) {
            return $response;
        }

        return $response
            ->rootView('public-funnel')
            ->withViewData([
                'grapesBuiltPage' => true,
                'grapesPageCss' => $pageCss,
                'grapesFontsUrl' => self::GRAPES_GOOGLE_FONTS_URL,
            ]);
    }

    public function webinar(
        Request $request,
        string $username,
        string $slug,
        PublicFunnelResolver $resolver
    ): Response|HttpResponse {
        $funnel = $resolver->resolve($username, $slug);

        if (! $funnel) {
            abort(404);
        }

        $this->pageViewRecorder->record($funnel, 'webinar', $request);

        $webinarPage = $funnel->pages->firstWhere('page_type', 'webinar');
        $resolved = $this->webinarPublicChat->resolveConversation($request, (int) $funnel->id);

        return Inertia::render('public/Webinar', [
            'funnel' => [
                'name' => $funnel->name,
                'slug' => $funnel->slug,
                'owner' => $username,
                'settings' => $funnel->settings,
            ],
            'page' => $webinarPage?->schema ?? [],
            'chatMessages' => $this->webinarPublicChat->attendeeMessages($funnel, $request),
            'chatEndpoints' => [
                'fetch' => route('public.chat.messages', compact('username', 'slug')),
                'send' => route('public.chat.send', compact('username', 'slug')),
            ],
            'analyticsEndpoint' => route('public.webinar.stats', compact('username', 'slug')),
            'chatRealtime' => [
                'funnel_id' => (int) $funnel->id,
                'conversation_key' => (string) $resolved['conversation_key'],
            ],
        ]);
    }

    public function trackVideoStats(
        Request $request,
        string $username,
        string $slug,
        PublicFunnelResolver $resolver
    ): JsonResponse {
        $funnel = $resolver->resolve($username, $slug);
        if (! $funnel) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $payload = $request->validate([
            'session_key' => ['required', 'string', 'max:80'],
            'event' => ['required', 'string', 'in:access,heartbeat,milestone_60,milestone_50,milestone_100'],
            'watched_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        $now = now();
        $watchedSeconds = (int) ($payload['watched_seconds'] ?? 0);
        $event = (string) $payload['event'];

        $stat = FunnelVideoViewStat::query()->firstOrCreate(
            [
                'funnel_id' => $funnel->id,
                'session_key' => $payload['session_key'],
            ],
            [
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'watched_seconds' => $watchedSeconds,
            ]
        );

        $stat->last_seen_at = $now;
        $stat->watched_seconds = max((int) $stat->watched_seconds, $watchedSeconds);

        if ($event === 'milestone_60') {
            $stat->reached_60s = true;
        }
        if ($event === 'milestone_50') {
            $stat->reached_50_percent = true;
        }
        if ($event === 'milestone_100') {
            $stat->reached_100_percent = true;
        }

        $stat->save();

        return response()->json(['ok' => true]);
    }
}
