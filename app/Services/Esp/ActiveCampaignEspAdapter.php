<?php

namespace App\Services\Esp;

use Illuminate\Support\Facades\Http;

class ActiveCampaignEspAdapter implements EspProviderAdapter
{
    public function subscribe(array $payload, array $credentials, array $config): array
    {
        $apiUrl = rtrim((string) ($credentials['api_url'] ?? ''), '/');
        $apiKey = (string) ($credentials['api_key'] ?? '');
        $listId = (string) ($credentials['list_id'] ?? '');

        if (! $apiUrl || ! $apiKey || ! $listId) {
            return ['ok' => false, 'message' => 'API URL, API key, and List ID are required.'];
        }

        $nameParts  = explode(' ', $payload['name'] ?? '', 2);
        $headers    = ['Api-Token' => $apiKey];

        // Upsert contact
        $contactRes = Http::withHeaders($headers)
            ->timeout(10)
            ->post("{$apiUrl}/api/3/contacts", [
                'contact' => [
                    'email'     => $payload['email'],
                    'firstName' => $nameParts[0] ?? '',
                    'lastName'  => $nameParts[1] ?? '',
                ],
            ]);

        if (! $contactRes->successful()) {
            return [
                'ok'      => false,
                'message' => $contactRes->json('errors.0.title') ?? 'ActiveCampaign: contact upsert failed.',
                'status'  => $contactRes->status(),
            ];
        }

        $contactId = $contactRes->json('contact.id');

        // Add to list
        $listRes = Http::withHeaders($headers)
            ->timeout(10)
            ->post("{$apiUrl}/api/3/contactLists", [
                'contactList' => [
                    'list'    => $listId,
                    'contact' => $contactId,
                    'status'  => 1,
                ],
            ]);

        return [
            'ok'      => $listRes->successful(),
            'message' => $listRes->successful() ? 'Subscribed.' : ($listRes->json('errors.0.title') ?? 'ActiveCampaign: list add failed.'),
            'status'  => $listRes->status(),
        ];
    }

    public function testConnection(array $credentials): array
    {
        $apiUrl = rtrim((string) ($credentials['api_url'] ?? ''), '/');
        $apiKey = (string) ($credentials['api_key'] ?? '');

        if (! $apiUrl || ! $apiKey) {
            return ['ok' => false, 'message' => 'API URL and API key are required.'];
        }

        $response = Http::withHeaders(['Api-Token' => $apiKey])
            ->timeout(8)
            ->get("{$apiUrl}/api/3/lists", ['limit' => 1]);

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful()
                ? 'Connected to ActiveCampaign. '.($response->json('meta.total') ?? '').' lists found.'
                : ($response->json('errors.0.title') ?? 'Could not connect to ActiveCampaign.'),
        ];
    }
}
