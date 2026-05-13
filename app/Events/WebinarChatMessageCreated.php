<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebinarChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public int $funnelId,
        public string $conversationKey,
        public array $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("webinar.{$this->funnelId}.{$this->conversationKey}")];
    }

    public function broadcastAs(): string
    {
        return 'webinar.chat.message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'funnel_id' => $this->funnelId,
            'conversation_key' => $this->conversationKey,
            'message' => $this->message,
        ];
    }
}

