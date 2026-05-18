<?php

namespace App\Services\Zernio;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ZernioClient
{
    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.zernio.enabled', true) && $this->isConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    public function createProfile(string $name, ?string $description = null): array
    {
        $payload = ['name' => $name];
        if ($description !== null && $description !== '') {
            $payload['description'] = $description;
        }

        return $this->unwrap($this->request()->post('/v1/profiles', $payload));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listProfiles(): array
    {
        $body = $this->unwrap($this->request()->get('/v1/profiles'));

        return is_array($body['data'] ?? null) ? $body['data'] : (is_array($body) ? $body : []);
    }

    /**
     * @return array{authUrl: string, state?: string}
     */
    public function getConnectUrl(string $platform, string $profileId, string $redirectUrl): array
    {
        $body = $this->unwrap($this->request()->get("/v1/connect/{$platform}", [
            'profileId' => $profileId,
            'redirect_url' => $redirectUrl,
        ]));

        $authUrl = $body['authUrl'] ?? null;
        if (! is_string($authUrl) || $authUrl === '') {
            throw new \RuntimeException('Zernio did not return an OAuth URL.');
        }

        return [
            'authUrl' => $authUrl,
            'state' => is_string($body['state'] ?? null) ? $body['state'] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInboxCommentPosts(array $query = []): array
    {
        $body = $this->unwrap($this->request()->get('/v1/inbox/comments', $query));

        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPostComments(string $postId, string $accountId, array $query = []): array
    {
        $body = $this->unwrap($this->request()->get(
            '/v1/inbox/comments/'.rawurlencode($postId),
            array_merge(['accountId' => $accountId], $query)
        ));

        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    /**
     * @return array{success: bool, external_id?: string|null, error?: string, rate_limited?: bool}
     */
    public function replyToPost(
        string $postId,
        string $accountId,
        string $message,
        ?string $commentId = null,
    ): array {
        $payload = [
            'accountId' => $accountId,
            'message' => $message,
        ];

        if ($commentId !== null && $commentId !== '') {
            $payload['commentId'] = $commentId;
        }

        try {
            $response = $this->request()->post(
                '/v1/inbox/comments/'.rawurlencode($postId),
                $payload
            );

            if ($response->status() === 429) {
                return ['success' => false, 'error' => 'Zernio rate limited.', 'rate_limited' => true];
            }

            if (in_array($response->status(), [401, 403], true)) {
                return ['success' => false, 'error' => 'Zernio auth error; reconnect your account.'];
            }

            if ($response->successful()) {
                $data = $response->json('data') ?? $response->json();
                $externalId = is_array($data)
                    ? ($data['commentId'] ?? $data['id'] ?? null)
                    : null;

                return [
                    'success' => true,
                    'external_id' => is_string($externalId) ? $externalId : null,
                ];
            }

            $error = $response->json('error');
            $message = is_string($error) ? $error : 'Zernio API error ('.$response->status().').';

            Log::warning('ZernioClient::replyToPost failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 400, ''),
            ]);

            return ['success' => false, 'error' => $message];
        } catch (\Throwable $e) {
            Log::error('ZernioClient::replyToPost exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function disconnectAccount(string $accountId): bool
    {
        $response = $this->request()->delete('/v1/accounts/'.rawurlencode($accountId));

        return $response->successful() || $response->status() === 404;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAccountsForProfile(string $profileId): array
    {
        $body = $this->unwrap($this->request()->get('/v1/accounts', [
            'profileId' => $profileId,
        ]));

        if (is_array($body['accounts'] ?? null)) {
            return $body['accounts'];
        }

        if (is_array($body['data'] ?? null)) {
            return $body['data'];
        }

        return is_array($body) && array_is_list($body) ? $body : [];
    }

    /**
     * Complete OAuth after the provider redirects to your app with code + state.
     *
     * @return array<string, mixed>
     */
    public function completeOAuthConnection(
        string $platform,
        string $code,
        string $state,
        string $profileId,
    ): array {
        return $this->unwrap($this->request()->post('/v1/connect/'.$platform, [
            'code' => $code,
            'state' => $state,
            'profileId' => $profileId,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function unwrap(Response $response): array
    {
        if ($response->status() === 429) {
            throw new ZernioRateLimitedException('Zernio rate limited.');
        }

        if (! $response->successful()) {
            throw ZernioApiException::fromResponse($response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.zernio.base_url'), '/'))
            ->withToken($this->apiKey())
            ->acceptJson()
            ->timeout((int) config('services.zernio.timeout', 60));
    }

    private function apiKey(): string
    {
        return (string) config('services.zernio.api_key', '');
    }
}
