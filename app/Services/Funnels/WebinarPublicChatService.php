<?php

namespace App\Services\Funnels;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Funnel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WebinarPublicChatService
{
    public const WELCOME_AUTHOR = 'Host';

    public const WELCOME_MESSAGE = "Hello there!\nYou're welcome to this live training.";

    /**
     * @return array{conversation_key: string, attendee_name: string, attendee_email: ?string}
     */
    public function resolveConversation(Request $request, int $funnelId): array
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

    /**
     * Idempotent default greeting for this attendee thread (persists in DB).
     */
    public function ensureWelcomeMessage(?ChatRoom $room, string $conversationKey): void
    {
        if (! $room) {
            return;
        }

        $room->messages()->firstOrCreate(
            [
                'conversation_key' => $conversationKey,
                'participant_role' => 'owner',
                'is_seeded' => true,
                'message' => self::WELCOME_MESSAGE,
            ],
            [
                'author_name' => self::WELCOME_AUTHOR,
                'attendee_name' => null,
                'attendee_email' => null,
                'published_at' => now(),
            ]
        );
    }

    /**
     * Messages for the current session’s webinar chat (and ensure welcome exists).
     *
     * @return Collection<int, ChatMessage>
     */
    public function attendeeMessages(Funnel $funnel, Request $request): Collection
    {
        $conversation = $this->resolveConversation($request, (int) $funnel->id);
        $room = $funnel->chatRoom()->firstOrCreate(
            ['funnel_id' => $funnel->id],
            [
                'mode' => 'hybrid',
                'is_active' => true,
            ],
        );
        $this->ensureWelcomeMessage($room, $conversation['conversation_key']);

        return $room->messages()
            ->where('conversation_key', $conversation['conversation_key'])
            ->orderBy('id')
            ->limit(300)
            ->get();
    }
}
