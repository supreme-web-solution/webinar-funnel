<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

final class TrafficAiLogger
{
    public static function info(string $message, array $context = []): void
    {
        Log::info('TrafficAi: '.$message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        Log::warning('TrafficAi: '.$message, $context);
    }
}
