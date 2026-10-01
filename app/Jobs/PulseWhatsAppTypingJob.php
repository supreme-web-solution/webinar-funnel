<?php

namespace App\Jobs;

use App\Services\Zernio\ZernioClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class PulseWhatsAppTypingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public string $conversationId)
    {
        $this->onQueue('default');
    }

    public function handle(ZernioClient $zernio): void
    {
        $key = 'whatsapp-typing:'.$this->conversationId;
        $payload = Cache::get($key);
        if (! is_array($payload)) {
            return;
        }

        $zernio->sendTypingIndicator(
            (string) ($payload['account_id'] ?? ''),
            (string) ($payload['conversation_id'] ?? $this->conversationId),
        );

        if (! Cache::has($key)) {
            return;
        }

        self::dispatch($this->conversationId)->delay(now()->addSeconds(12));
    }
}
