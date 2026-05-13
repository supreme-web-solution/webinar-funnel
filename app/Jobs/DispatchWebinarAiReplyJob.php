<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchWebinarAiReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 45;

    public function __construct(
        public int $chatMessageId,
    ) {
        $this->onQueue('webinar-ai');
    }

    public function handle(): void
    {
        $incoming = ChatMessage::query()
            ->with(['chatRoom.funnel.settings'])
            ->find($this->chatMessageId);

        if (! $incoming || $incoming->participant_role !== 'guest') {
            return;
        }

        $settings = $incoming->chatRoom?->funnel?->settings;
        if (! $settings || ! $settings->webinar_ai_enabled || ! $settings->webinar_ai_auto_reply_enabled) {
            return;
        }

        GenerateWebinarAiReplyJob::dispatch($incoming->id)->delay(now()->addSecond());
    }
}

