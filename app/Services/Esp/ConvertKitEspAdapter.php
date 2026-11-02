<?php

namespace App\Services\Esp;

use App\Services\Esp\Concerns\FormatsEmailSequenceHtml;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ConvertKitEspAdapter implements EspProviderAdapter, EspSequenceAdapter
{
    use FormatsEmailSequenceHtml;

    private const V4 = 'https://api.kit.com/v4';

    private const V3 = 'https://api.convertkit.com/v3';

    public function subscribe(array $payload, array $credentials, array $config): array
    {
        $auth = $this->credentials($credentials);
        $formId = $auth['form_id'];

        if ($auth['key'] === '' || $formId === '') {
            return ['ok' => false, 'message' => 'API key and Form ID are required.'];
        }

        $nameParts = explode(' ', trim((string) ($payload['name'] ?? '')), 2);
        $email = (string) ($payload['email'] ?? '');
        $firstName = $nameParts[0] ?? '';

        if ($auth['version'] === 'v4') {
            return $this->subscribeV4($auth['key'], $formId, $email, $firstName);
        }

        return $this->subscribeV3($auth, $formId, $email, $firstName);
    }

    public function testConnection(array $credentials): array
    {
        $auth = $this->credentials($credentials);

        if ($auth['key'] === '') {
            return ['ok' => false, 'message' => 'API key is required.'];
        }

        if ($auth['version'] === 'v4') {
            $response = Http::timeout(8)
                ->withHeaders($this->v4Headers($auth['key']))
                ->acceptJson()
                ->get(self::V4.'/account');
        } else {
            $query = $auth['param'] === 'api_secret'
                ? ['api_secret' => $auth['key']]
                : ['api_key' => $auth['key']];

            $response = Http::timeout(8)
                ->acceptJson()
                ->get(self::V3.'/account', $query);
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => $this->errorMessage($response, 'Could not connect to Kit.'),
            ];
        }

        $name = $response->json('account.name')
            ?? $response->json('name')
            ?? $response->json('user.name')
            ?? 'Kit';

        return [
            'ok' => true,
            'message' => 'Connected: '.(is_string($name) ? $name : 'Kit'),
        ];
    }

    /**
     * @param  array<int, array{subject: string, body: string, sequence_key?: string, sort_order?: int}>  $emails
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $config
     * @return array{ok: bool, message: string, uploaded?: int, details?: array<int, string>}
     */
    public function uploadSequence(array $emails, array $credentials, array $config): array
    {
        $auth = $this->credentials($credentials);

        if ($auth['key'] === '') {
            return ['ok' => false, 'message' => 'API key is required.'];
        }

        $uploaded = 0;
        $details = [];

        foreach ($emails as $index => $email) {
            $subject = (string) ($email['subject'] ?? 'Update');
            $html = $this->bodyToHtml((string) ($email['body'] ?? ''));

            $response = $auth['version'] === 'v4'
                ? $this->createV4Broadcast($auth['key'], $subject, $html)
                : Http::timeout(20)->acceptJson()->post(self::V3.'/broadcasts', [
                    $auth['param'] => $auth['key'],
                    'subject' => $subject,
                    'content' => $html,
                ]);

            if ($response->successful()) {
                $uploaded++;
                $details[] = 'Email '.($index + 1).': broadcast draft created';
            } else {
                $details[] = 'Email '.($index + 1).': '.$this->errorMessage($response, 'failed');
            }
        }

        return [
            'ok' => $uploaded > 0,
            'message' => $uploaded > 0
                ? "{$uploaded} swipes uploaded as Kit broadcast drafts."
                : 'Kit upload failed for all emails.',
            'uploaded' => $uploaded,
            'details' => $details,
        ];
    }

    /**
     * @param  array{version: string, key: string, param: string, form_id: string}  $auth
     * @return array{ok: bool, message: string, status?: int}
     */
    private function subscribeV3(array $auth, string $formId, string $email, string $firstName): array
    {
        $response = Http::timeout(10)
            ->acceptJson()
            ->post(self::V3."/forms/{$formId}/subscribe", [
                $auth['param'] => $auth['key'],
                'email' => $email,
                'first_name' => $firstName,
            ]);

        if (! $response->successful() || $response->json('message') === 'error') {
            return $this->failure($response, 'Kit subscribe failed.');
        }

        return [
            'ok' => true,
            'message' => 'Subscribed.',
            'status' => $response->status(),
        ];
    }

    /**
     * @return array{ok: bool, message: string, status?: int}
     */
    private function subscribeV4(string $apiKey, string $formId, string $email, string $firstName): array
    {
        $create = Http::timeout(10)
            ->withHeaders($this->v4Headers($apiKey))
            ->acceptJson()
            ->post(self::V4.'/subscribers', array_filter([
                'email_address' => $email,
                'first_name' => $firstName,
                // Confirmed on create. Kit will not change state later, and unconfirmed
                // subscribers stay off the form's subscriber count.
                'state' => 'active',
            ], fn ($value) => $value !== ''));

        if (! $create->successful()) {
            return $this->failure($create, 'Could not create the Kit subscriber.');
        }

        $add = Http::timeout(10)
            ->withHeaders($this->v4Headers($apiKey))
            ->acceptJson()
            ->post(self::V4."/forms/{$formId}/subscribers", [
                'email_address' => $email,
            ]);

        if (! $add->successful()) {
            return $this->failure($add, 'Subscriber was created but could not be added to the Kit form.');
        }

        return [
            'ok' => true,
            'message' => 'Subscribed.',
            'status' => $add->status(),
        ];
    }

    private function createV4Broadcast(string $apiKey, string $subject, string $html): Response
    {
        return Http::timeout(20)
            ->withHeaders($this->v4Headers($apiKey))
            ->acceptJson()
            ->post(self::V4.'/broadcasts', [
                'subject' => $subject,
                'content' => $html,
                'description' => $subject,
                'preview_text' => '',
                'public' => false,
                'published_at' => now()->utc()->toIso8601String(),
                'send_at' => null,
                'subscriber_filter' => [
                    [
                        'all' => [],
                        'any' => null,
                        'none' => null,
                    ],
                ],
            ]);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{version: string, key: string, param: string, form_id: string}
     */
    private function credentials(array $credentials): array
    {
        $apiKey = trim((string) ($credentials['api_key'] ?? ''));
        $apiSecret = trim((string) ($credentials['api_secret'] ?? ''));
        $formId = trim((string) ($credentials['form_id'] ?? ''));

        if ($this->isV4Key($apiKey)) {
            return ['version' => 'v4', 'key' => $apiKey, 'param' => 'api_key', 'form_id' => $formId];
        }

        if ($this->isV4Key($apiSecret)) {
            return ['version' => 'v4', 'key' => $apiSecret, 'param' => 'api_key', 'form_id' => $formId];
        }

        if ($apiSecret !== '') {
            return ['version' => 'v3', 'key' => $apiSecret, 'param' => 'api_secret', 'form_id' => $formId];
        }

        return ['version' => 'v3', 'key' => $apiKey, 'param' => 'api_key', 'form_id' => $formId];
    }

    private function isV4Key(string $key): bool
    {
        return str_starts_with(strtolower($key), 'kit_');
    }

    /**
     * @return array<string, string>
     */
    private function v4Headers(string $apiKey): array
    {
        return ['X-Kit-Api-Key' => $apiKey];
    }

    /**
     * @return array{ok: bool, message: string, status: int}
     */
    private function failure(Response $response, string $fallback): array
    {
        return [
            'ok' => false,
            'message' => $this->errorMessage($response, $fallback),
            'status' => $response->status(),
        ];
    }

    private function errorMessage(Response $response, string $fallback): string
    {
        $errors = $response->json('errors');

        if (is_array($errors)) {
            $parts = array_values(array_filter($errors, 'is_string'));

            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        $message = $response->json('message') ?? $response->json('error');

        return is_string($message) && $message !== '' && $message !== 'error'
            ? $message
            : $fallback;
    }
}
