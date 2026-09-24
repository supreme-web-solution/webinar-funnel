<?php

namespace App\Jobs;

use App\Services\AiEmployee\WhatsAppChannelService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWhatsAppInboundJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload)
    {
        $this->onQueue((string) config('ai_employee.queue', 'webinar-ai'));
    }

    public function handle(WhatsAppChannelService $whatsApp): void
    {
        $whatsApp->handleInbound($this->payload);
    }
}
