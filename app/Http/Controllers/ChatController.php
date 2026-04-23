<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Funnel;
use App\Services\Funnels\PublicFunnelResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function manage(Funnel $funnel): Response
    {
        $this->authorizeFunnel($funnel);
        $funnel->load(['user:id,username']);

        $username = $funnel->user->username ?? 'user-'.$funnel->user_id;
        $conversations = $funnel->chatRoom?->messages()
            ->selectRaw('conversation_key, MAX(id) as latest_id')
            ->groupBy('conversation_key')
            ->orderByDesc('latest_id')
            ->limit(500)
            ->get()
            ->map(function ($row) use ($funnel) {
                $latestMessage = ChatMessage::query()->whereKey((int) $row->latest_id)->first();
                $count = $funnel->chatRoom?->messages()
                    ->where('conversation_key', $row->conversation_key)
                    ->count() ?? 0;

                return [
                    'conversation_key' => $row->conversation_key,
                    'attendee_name' => $latestMessage?->attendee_name ?? 'Anonymous attendee',
                    'attendee_email' => $latestMessage?->attendee_email,
                    'latest_message' => $latestMessage?->message,
                    'message_count' => $count,
                    'latest_id' => $latestMessage?->id,
                ];
            })
            ->values() ?? collect();

        return Inertia::render('funnels/Chat', [
            'funnel' => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'slug' => $funnel->slug,
                'status' => $funnel->status,
            ],
            'conversations' => $conversations,
            'publicLinks' => [
                'webinar' => route('public.webinar', compact('username') + ['slug' => $funnel->slug]),
            ],
        ]);
    }

    public function ownerMessages(Request $request, Funnel $funnel): JsonResponse
    {
        $this->authorizeFunnel($funnel);
        $conversationKey = $request->query('conversation_key');
        abort_unless(is_string($conversationKey) && $conversationKey !== '', 422, 'conversation_key is required');

        return response()->json([
            'messages' => $funnel->chatRoom?->messages()
                ->where('conversation_key', $conversationKey)
                ->orderBy('id')
                ->limit(300)
                ->get() ?? [],
        ]);
    }

    public function ownerConversations(Funnel $funnel): JsonResponse
    {
        $this->authorizeFunnel($funnel);

        $conversations = $funnel->chatRoom?->messages()
            ->selectRaw('conversation_key, MAX(id) as latest_id')
            ->groupBy('conversation_key')
            ->orderByDesc('latest_id')
            ->limit(500)
            ->get()
            ->map(function ($row) use ($funnel) {
                $latestMessage = ChatMessage::query()->whereKey((int) $row->latest_id)->first();
                $count = $funnel->chatRoom?->messages()
                    ->where('conversation_key', $row->conversation_key)
                    ->count() ?? 0;

                return [
                    'conversation_key' => $row->conversation_key,
                    'attendee_name' => $latestMessage?->attendee_name ?? 'Anonymous attendee',
                    'attendee_email' => $latestMessage?->attendee_email,
                    'latest_message' => $latestMessage?->message,
                    'message_count' => $count,
                    'latest_id' => $latestMessage?->id,
                ];
            })
            ->values() ?? collect();

        return response()->json([
            'conversations' => $conversations,
        ]);
    }

    public function deleteConversation(Request $request, Funnel $funnel): JsonResponse
    {
        $this->authorizeFunnel($funnel);
        $conversationKey = $request->validate([
            'conversation_key' => ['required', 'string', 'max:120'],
        ])['conversation_key'];

        $deleted = $funnel->chatRoom?->messages()
            ->where('conversation_key', $conversationKey)
            ->delete() ?? 0;

        return response()->json(['deleted' => $deleted]);
    }

    public function ownerSend(Request $request, Funnel $funnel): JsonResponse
    {
        $this->authorizeFunnel($funnel);
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'conversation_key' => ['required', 'string', 'max:120'],
        ]);

        $room = $funnel->chatRoom()->firstOrCreate([
            'funnel_id' => $funnel->id,
        ], [
            'mode' => 'hybrid',
            'is_active' => true,
        ]);

        $message = $room->messages()->create([
            'author_name' => $request->user()->name,
            'conversation_key' => $validated['conversation_key'],
            'participant_role' => 'owner',
            'attendee_name' => null,
            'attendee_email' => null,
            'message' => $validated['message'],
            'is_seeded' => false,
            'published_at' => now(),
        ]);

        return response()->json(['message' => $message], 201);
    }

    public function publicMessages(
        Request $request,
        string $username,
        string $slug,
        PublicFunnelResolver $resolver
    ): JsonResponse {
        $funnel = $resolver->resolve($username, $slug);
        abort_if(! $funnel, 404);

        $conversation = $this->resolveAttendeeConversation($request, $funnel->id);

        return response()->json([
            'messages' => $funnel->chatRoom?->messages()
                ->where('conversation_key', $conversation['conversation_key'])
                ->orderBy('id')
                ->limit(300)
                ->get() ?? [],
        ]);
    }

    public function publicSend(
        Request $request,
        string $username,
        string $slug,
        PublicFunnelResolver $resolver
    ): JsonResponse {
        $funnel = $resolver->resolve($username, $slug);
        abort_if(! $funnel, 404);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $room = $funnel->chatRoom()->firstOrCreate([
            'funnel_id' => $funnel->id,
        ], [
            'mode' => 'hybrid',
            'is_active' => true,
        ]);

        $conversation = $this->resolveAttendeeConversation($request, $funnel->id);

        $message = $room->messages()->create([
            'author_name' => $conversation['attendee_name'] ?? 'Webinar Attendee',
            'conversation_key' => $conversation['conversation_key'],
            'participant_role' => 'guest',
            'attendee_name' => $conversation['attendee_name'],
            'attendee_email' => $conversation['attendee_email'],
            'message' => $validated['message'],
            'is_seeded' => false,
            'published_at' => now(),
        ]);

        return response()->json(['message' => $message], 201);
    }

    private function authorizeFunnel(Funnel $funnel): void
    {
        abort_unless((int) auth()->id() === (int) $funnel->user_id, 403);
    }

    /**
     * @return array{conversation_key: string, attendee_name: string, attendee_email: ?string}
     */
    private function resolveAttendeeConversation(Request $request, int $funnelId): array
    {
        $leadSession = $request->session()->get("funnel_lead.{$funnelId}", []);
        $name = 'Anonymous attendee';
        $email = null;

        if (is_array($leadSession)) {
            $name = (string) ($leadSession['name'] ?? $name);
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
            'attendee_name' => $name,
            'attendee_email' => $email,
        ];
    }
}
