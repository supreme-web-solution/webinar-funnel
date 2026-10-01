<?php

namespace App\Console\Commands;

use App\Services\Zernio\ZernioClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PulseWhatsAppTypingCommand extends Command
{
    protected $signature = 'ai-employee:typing-pulse {token}';

    protected $description = 'Refresh the WhatsApp typing indicator until the reply is sent';

    public function handle(ZernioClient $zernio): int
    {
        $key = 'whatsapp-typing:'.$this->argument('token');

        for ($i = 0; $i < 12; $i++) {
            sleep(18);

            $payload = Cache::get($key);
            if (! is_array($payload)) {
                return self::SUCCESS;
            }

            $zernio->sendTypingIndicator(
                (string) ($payload['account_id'] ?? ''),
                (string) ($payload['conversation_id'] ?? ''),
            );
        }

        return self::SUCCESS;
    }
}
