<?php

namespace App\Jobs;

use App\Events\WebinarChatMessageCreated;
use App\Mail\WebinarAiNeedsAttention;
use App\Models\ChatMessage;
use App\Services\Funnels\WebinarAiAssistantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GenerateWebinarAiReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 90;

    public function __construct(
        public int $chatMessageId,
    ) {
        $this->onQueue('webinar-ai');
    }

    public function handle(WebinarAiAssistantService $assistant): void
    {
        $incoming = ChatMessage::query()
            ->with(['chatRoom.funnel.settings', 'chatRoom.funnel.user'])
            ->find($this->chatMessageId);

        if (! $incoming || $incoming->participant_role !== 'guest') {
            return;
        }

        $room = $incoming->chatRoom;
        $funnel = $room?->funnel;
        $settings = $funnel?->settings;
        $owner = $funnel?->user;
        if (! $room || ! $funnel || ! $settings || ! $owner) {
            return;
        }

        if (! $settings->webinar_ai_enabled || ! $settings->webinar_ai_auto_reply_enabled) {
            return;
        }

        $history = $room->messages()
            ->where('conversation_key', $incoming->conversation_key)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $m): string => ($m->participant_role === 'owner' ? 'Host: ' : 'Guest: ').$m->message)
            ->values()
            ->all();

        $result = $assistant->generateReply($funnel, (string) $incoming->message, $history);
        $reply = $result['reply'] ?? null;
        $reason = $result['reason'] ?? 'unknown';

        if (is_string($reply) && trim($reply) !== '') {
            $outgoing = $room->messages()->create([
                'author_name' => trim((string) ($settings->webinar_ai_assistant_name ?? '')) ?: 'Webinar Assistant',
                'conversation_key' => $incoming->conversation_key,
                'participant_role' => 'owner',
                'attendee_name' => null,
                'attendee_email' => null,
                'message' => $reply,
                'is_seeded' => false,
                'published_at' => now(),
            ]);

            WebinarChatMessageCreated::dispatch(
                (int) $funnel->id,
                (string) $outgoing->conversation_key,
                [
                    'id' => (int) $outgoing->id,
                    'author_name' => (string) $outgoing->author_name,
                    'participant_role' => $outgoing->participant_role,
                    'message' => (string) $outgoing->message,
                    'conversation_key' => (string) $outgoing->conversation_key,
                    'attendee_name' => $outgoing->attendee_name,
                    'attendee_email' => $outgoing->attendee_email,
                    'published_at' => optional($outgoing->published_at)->toISOString(),
                ]
            );
            return;
        }

        if (! $owner->email) {
            return;
        }

        try {
            Mail::to($owner->email)->send(new WebinarAiNeedsAttention($funnel, $incoming, $reason));
        } catch (\Throwable $e) {
            Log::warning('Webinar AI fallback email failed', [
                'chat_message_id' => $incoming->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

