<?php

namespace App\Services\Esp;

use Illuminate\Support\Facades\Http;

class GetResponseEspAdapter implements EspProviderAdapter
{
    private const BASE = 'https://api.getresponse.com/v3';

    public function subscribe(array $payload, array $credentials, array $config): array
    {
        $apiKey     = (string) ($credentials['api_key'] ?? '');
        $campaignId = (string) ($credentials['campaign_id'] ?? '');

        if (! $apiKey || ! $campaignId) {
            return ['ok' => false, 'message' => 'API key and Campaign ID are required.'];
        }

        $response = Http::withHeaders(['X-Auth-Token' => "api-key {$apiKey}"])
            ->timeout(10)
            ->post(self::BASE.'/contacts', [
                'email'    => $payload['email'],
                'name'     => $payload['name'] ?? '',
                'campaign' => ['campaignId' => $campaignId],
            ]);

        // 409 = already exists — still a success
        $ok = $response->successful() || $response->status() === 409;

        return [
            'ok'      => $ok,
            'message' => $ok ? 'Subscribed.' : ($response->json('message') ?? 'GetResponse error.'),
            'status'  => $response->status(),
        ];
    }

    public function testConnection(array $credentials): array
    {
        $apiKey = (string) ($credentials['api_key'] ?? '');

        if (! $apiKey) {
            return ['ok' => false, 'message' => 'API key is required.'];
        }

        $response = Http::withHeaders(['X-Auth-Token' => "api-key {$apiKey}"])
            ->timeout(8)
            ->get(self::BASE.'/accounts');

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful()
                ? 'Connected to GetResponse account: '.($response->json('email') ?? '✓')
                : ($response->json('message') ?? 'Could not connect to GetResponse.'),
        ];
    }
}
