<?php

namespace App\Services\Esp;

use Illuminate\Support\Facades\Http;

/**
 * Sendinblue / Brevo adapter (same API, rebranded).
 */
class BrevoEspAdapter implements EspProviderAdapter
{
    private const BASE = 'https://api.brevo.com/v3';

    public function subscribe(array $payload, array $credentials, array $config): array
    {
        $apiKey = (string) ($credentials['api_key'] ?? '');
        $listId = (int) ($credentials['list_id'] ?? 0);

        if (! $apiKey || ! $listId) {
            return ['ok' => false, 'message' => 'API key and List ID are required.'];
        }

        $nameParts = explode(' ', $payload['name'] ?? '', 2);

        $response = Http::withHeaders(['api-key' => $apiKey])
            ->timeout(10)
            ->post(self::BASE.'/contacts', [
                'email'         => $payload['email'],
                'attributes'    => [
                    'FIRSTNAME' => $nameParts[0] ?? '',
                    'LASTNAME'  => $nameParts[1] ?? '',
                ],
                'listIds'       => [$listId],
                'updateEnabled' => true,
            ]);

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful() ? 'Subscribed.' : ($response->json('message') ?? 'Brevo error.'),
            'status'  => $response->status(),
        ];
    }

    public function testConnection(array $credentials): array
    {
        $apiKey = (string) ($credentials['api_key'] ?? '');

        if (! $apiKey) {
            return ['ok' => false, 'message' => 'API key is required.'];
        }

        $response = Http::withHeaders(['api-key' => $apiKey])
            ->timeout(8)
            ->get(self::BASE.'/account');

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful()
                ? 'Connected: '.($response->json('email') ?? '✓')
                : ($response->json('message') ?? 'Could not connect to Brevo.'),
        ];
    }
}
