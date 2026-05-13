<?php

namespace App\Http\Controllers;

use App\Models\FunnelVideoViewStat;
use App\Services\Funnels\PublicFunnelResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicFunnelController extends Controller
{
    public function optin(
        string $username,
        string $slug,
        PublicFunnelResolver $resolver
    ): Response|HttpResponse {
        $funnel = $resolver->resolve($username, $slug);

        if (! $funnel) {
            abort(404);
        }

        $optinPage = $funnel->pages->firstWhere('page_type', 'optin');
        $schema    = $optinPage?->schema ?? [];

        return Inertia::render('public/Optin', [
            'funnel' => [
                'name'  => $funnel->name,
                'slug'  => $funnel->slug,
                'owner' => $username,
            ],
            'pageHtml' => $schema['html'] ?? null,
            'pageCss'  => $schema['css']  ?? null,
            // Legacy fallback keys kept for old funnel schemas
            'page' => $schema,
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

        $webinarPage = $funnel->pages->firstWhere('page_type', 'webinar');
        $conversation = $this->resolveAttendeeConversation($request, (int) $funnel->id);

        return Inertia::render('public/Webinar', [
            'funnel' => [
                'name' => $funnel->name,
                'slug' => $funnel->slug,
                'owner' => $username,
                'settings' => $funnel->settings,
            ],
            'page' => $webinarPage?->schema ?? [],
            'chatMessages' => $funnel->chatRoom?->messages()->orderBy('id')->limit(300)->get() ?? [],
            'chatEndpoints' => [
                'fetch' => route('public.chat.messages', compact('username', 'slug')),
                'send' => route('public.chat.send', compact('username', 'slug')),
            ],
            'analyticsEndpoint' => route('public.webinar.stats', compact('username', 'slug')),
            'chatRealtime' => [
                'funnel_id' => (int) $funnel->id,
                'conversation_key' => (string) $conversation['conversation_key'],
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

    /**
     * @return array{conversation_key: string}
     */
    private function resolveAttendeeConversation(Request $request, int $funnelId): array
    {
        $leadSession = $request->session()->get("funnel_lead.{$funnelId}", []);
        $email = null;

        if (is_array($leadSession)) {
            $email = ! empty($leadSession['email']) ? (string) $leadSession['email'] : null;
        }

        $conversationKey = $request->session()->get("funnel_chat_conversation.{$funnelId}");

        if (! is_string($conversationKey) || $conversationKey === '') {
            $seed = $email
                ? Str::lower(trim($email))
                : (string) $request->session()->getId();
            $conversationKey = substr(hash('sha256', $funnelId.'|'.$seed), 0, 48);
            $request->session()->put("funnel_chat_conversation.{$funnelId}", $conversationKey);
        }

        return [
            'conversation_key' => $conversationKey,
        ];
    }
}
