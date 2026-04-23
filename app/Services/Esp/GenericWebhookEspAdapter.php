<?php

namespace App\Services\Esp;

use Illuminate\Support\Facades\Http;

class GenericWebhookEspAdapter implements EspProviderAdapter
{
    public function subscribe(array $payload, array $credentials, array $config): array
    {
        $url = (string) ($credentials['webhook_url'] ?? '');

        if (! $url) {
            return ['ok' => false, 'message' => 'Missing webhook_url credential.'];
        }

        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];

        if (! empty($credentials['api_key'])) {
            $headers['Authorization'] = 'Bearer '.$credentials['api_key'];
        }

        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->post($url, [
                'lead'   => $payload,
                'config' => $config,
            ]);

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful() ? 'Delivered to webhook.' : "Webhook returned HTTP {$response->status()}.",
            'status'  => $response->status(),
        ];
    }

    public function testConnection(array $credentials): array
    {
        $url = (string) ($credentials['webhook_url'] ?? '');

        if (! $url) {
            return ['ok' => false, 'message' => 'Webhook URL is required.'];
        }

        // Send a test ping payload
        $response = Http::timeout(8)->post($url, ['test' => true, 'source' => 'dfy-webinar-forge']);

        return [
            'ok'      => $response->status() < 500,
            'message' => $response->status() < 500
                ? "Webhook reachable (HTTP {$response->status()})."
                : "Webhook returned HTTP {$response->status()}.",
        ];
    }
}
