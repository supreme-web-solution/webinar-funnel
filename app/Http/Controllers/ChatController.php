<?php

namespace App\Http\Controllers;

use App\Events\WebinarChatMessageCreated;
use App\Jobs\DispatchWebinarAiReplyJob;
use App\Models\ChatMessage;
use App\Models\Funnel;
use App\Services\Funnels\PublicFunnelResolver;
use App\Services\Funnels\WebinarPublicChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(
        private WebinarPublicChatService $webinarPublicChat,
    ) {}

    public function manage(Funnel $funnel): Response
    {
        $this->authorizeFunnel($funnel);
        $funnel->load(['user:id,username']);

        $username = $funnel->user->username ?? 'user-'.$funnel->user_id;
        $conversations = $this->buildConversationsSummary($funnel, 500);

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

        $conversations = $this->buildConversationsSummary($funnel, 500);

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

        WebinarChatMessageCreated::dispatch(
            (int) $funnel->id,
            (string) $message->conversation_key,
            $this->toPublicMessagePayload($message)
        );

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

        return response()->json([
            'messages' => $this->webinarPublicChat->attendeeMessages($funnel, $request),
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

        $conversation = $this->webinarPublicChat->resolveConversation($request, $funnel->id);
        $this->webinarPublicChat->ensureWelcomeMessage($room, $conversation['conversation_key']);

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

        WebinarChatMessageCreated::dispatch(
            (int) $funnel->id,
            (string) $message->conversation_key,
            $this->toPublicMessagePayload($message)
        );

        if ($funnel->settings?->webinar_ai_enabled && $funnel->settings?->webinar_ai_auto_reply_enabled) {
            DispatchWebinarAiReplyJob::dispatch((int) $message->id)->delay(now()->addSeconds(2));
        }

        return response()->json(['message' => $message], 201);
    }

    private function authorizeFunnel(Funnel $funnel): void
    {
        $authId = (int) (Auth::id() ?? 0);
        abort_unless($authId > 0 && $authId === (int) $funnel->user_id, 403);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildConversationsSummary(Funnel $funnel, int $limit)
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
            $latestMessage = $latestMessages->get((int) $row->latest_id);

            return [
                'conversation_key' => $row->conversation_key,
                'attendee_name' => $latestMessage?->attendee_name ?? 'Anonymous attendee',
                'attendee_email' => $latestMessage?->attendee_email,
                'latest_message' => $latestMessage?->message,
                'message_count' => (int) $row->message_count,
                'latest_id' => $latestMessage?->id,
            ];
        })->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function toPublicMessagePayload(ChatMessage $message): array
    {
        return [
            'id' => (int) $message->id,
            'author_name' => (string) $message->author_name,
            'participant_role' => $message->participant_role,
            'message' => (string) $message->message,
            'conversation_key' => (string) $message->conversation_key,
            'attendee_name' => $message->attendee_name,
            'attendee_email' => $message->attendee_email,
            'published_at' => optional($message->published_at)->toISOString(),
        ];
    }
}
