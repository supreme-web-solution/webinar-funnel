<?php

namespace App\Services\Esp;

use Illuminate\Support\Facades\Http;

class ConvertKitEspAdapter implements EspProviderAdapter
{
    private const BASE = 'https://api.convertkit.com/v2';

    public function subscribe(array $payload, array $credentials, array $config): array
    {
        $apiSecret = (string) ($credentials['api_secret'] ?? '');
        $formId    = (string) ($credentials['form_id'] ?? '');

        if (! $apiSecret || ! $formId) {
            return ['ok' => false, 'message' => 'API secret and Form ID are required.'];
        }

        $nameParts = explode(' ', $payload['name'] ?? '', 2);

        $response = Http::timeout(10)
            ->post(self::BASE."/forms/{$formId}/subscribe", [
                'api_secret' => $apiSecret,
                'email'      => $payload['email'],
                'first_name' => $nameParts[0] ?? '',
            ]);

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful() ? 'Subscribed.' : ($response->json('message') ?? 'ConvertKit error.'),
            'status'  => $response->status(),
        ];
    }

    public function testConnection(array $credentials): array
    {
        $apiSecret = (string) ($credentials['api_secret'] ?? '');

        if (! $apiSecret) {
            return ['ok' => false, 'message' => 'API secret is required.'];
        }

        $response = Http::timeout(8)
            ->get(self::BASE.'/account', ['api_secret' => $apiSecret]);

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful()
                ? 'Connected: '.($response->json('name') ?? '✓')
                : ($response->json('message') ?? 'Could not connect to ConvertKit.'),
        ];
    }
}
