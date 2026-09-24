<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppInboundJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ZernioInboxWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('ai_employee.whatsapp.webhook_secret', config('services.zernio.webhook_secret', ''));
        $signature = (string) $request->header('X-Zernio-Signature', '');
        $raw = $request->getContent();

        if ($secret === '') {
            if (app()->isProduction()) {
                return response()->json(['error' => 'Webhook secret missing'], 401);
            }
        } elseif ($signature === '' || ! hash_equals(hash_hmac('sha256', $raw, $secret), $signature)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $payload = $request->json()->all();
        $eventId = (string) ($payload['id'] ?? '');
        if ($eventId !== '') {
            $cacheKey = 'zernio-webhook:'.$eventId;
            if (! Cache::add($cacheKey, 1, now()->addDay())) {
                return response()->json(['ok' => true, 'deduped' => true]);
            }
        }

        ProcessWhatsAppInboundJob::dispatch($payload);

        return response()->json(['ok' => true]);
    }
}
