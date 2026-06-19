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
     * Create and publish (or schedule) a post via Zernio.
     *
     * @param  list<array{platform: string, accountId: string}>  $platforms
     * @param  list<string>  $mediaUrls
     * @param  'image'|'video'|'gif'|null  $mediaType
     * @return array{
     *   success: bool,
     *   zernio_post_id?: string|null,
     *   published?: list<array{platform: string, external_id: string|null, url: string|null}>,
     *   error?: string,
     *   rate_limited?: bool
     * }
     */
    public function createPost(
        string $content,
        array $platforms,
        array $mediaUrls = [],
        ?string $mediaType = null,
        ?string $linkUrl = null,
        bool $publishNow = true,
        ?string $scheduledFor = null,
        ?string $timezone = null,
    ): array {
        $endpoint = (string) config('promotion.zernio.default_publish_endpoint', '/v1/posts');
        $mediaUrls = array_values(array_filter($mediaUrls, fn ($url) => is_string($url) && $url !== ''));

        $payload = [
            'content' => $content,
            'platforms' => $platforms,
        ];

        if ($mediaUrls !== []) {
            $payload['mediaUrls'] = $mediaUrls;
            $payload['mediaItems'] = $this->buildMediaItems($mediaUrls, $mediaType);
        }

        // linkUrl turns Facebook posts into link previews and drops the image attachment.
        if ($linkUrl !== null && $linkUrl !== '' && $mediaUrls === []) {
            $payload['linkUrl'] = $linkUrl;
        }
        if ($publishNow) {
            $payload['publishNow'] = true;
        } elseif ($scheduledFor !== null && $scheduledFor !== '') {
            $payload['scheduledFor'] = $scheduledFor;
            if ($timezone !== null && $timezone !== '') {
                $payload['timezone'] = $timezone;
            }
        }

        try {
            Log::info('ZernioClient::createPost request', [
                'platforms' => array_column($platforms, 'platform'),
                'publish_now' => $publishNow,
                'media_items' => count($payload['mediaItems'] ?? []),
                'content_length' => strlen($content),
            ]);

            $response = $this->request()->post($endpoint, $payload);

            if ($response->status() === 429) {
                return ['success' => false, 'error' => 'Zernio rate limited.', 'rate_limited' => true];
            }
            if (in_array($response->status(), [401, 403], true)) {
                return ['success' => false, 'error' => 'Zernio auth error; reconnect your account.'];
            }
            if (! $response->successful()) {
                $error = $this->parseApiErrorMessage($response);

                Log::warning('ZernioClient::createPost failed', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 600, ''),
                    'error' => $error,
                ]);

                return ['success' => false, 'error' => $error];
            }

            $post = $response->json('post');
            if (! is_array($post)) {
                Log::warning('ZernioClient::createPost unexpected response', [
                    'body' => Str::limit($response->body(), 600, ''),
                ]);

                return ['success' => false, 'error' => 'Unexpected response from Zernio (no post object).'];
            }

            $zernioPostId = is_string($post['_id'] ?? null) ? $post['_id'] : null;
            $published = $this->parsePlatformRows($post['platforms'] ?? []);

            $confirmed = $this->confirmedPlatformRows($published);

            if ($confirmed === [] && $publishNow && is_string($zernioPostId) && $zernioPostId !== '') {
                $immediateError = $this->firstPlatformError($published);
                if ($immediateError === null && $this->hasPendingPlatforms($published)) {
                    $waited = $this->waitForPostPlatforms(
                        $zernioPostId,
                        array_map(fn (array $target): string => (string) ($target['platform'] ?? ''), $platforms),
                    );
                    $published = $waited['platforms'];
                    $confirmed = $this->confirmedPlatformRows($published);
                    $immediateError = $waited['error'] ?? $this->firstPlatformError($published);
                }

                if ($confirmed === []) {
                    Log::warning('ZernioClient::createPost no platformPostId', [
                        'zernio_post_id' => $zernioPostId,
                        'message' => $response->json('message'),
                        'platforms' => $published,
                        'body' => Str::limit($response->body(), 600, ''),
                    ]);

                    return [
                        'success' => false,
                        'zernio_post_id' => $zernioPostId,
                        'published' => $published,
                        'error' => $immediateError ?? 'Zernio did not return a platform post ID. The post may have been saved as a draft only.',
                    ];
                }
            } elseif ($confirmed === [] && $publishNow) {
                Log::warning('ZernioClient::createPost no platformPostId', [
                    'zernio_post_id' => $zernioPostId,
                    'message' => $response->json('message'),
                    'platforms' => $published,
                    'body' => Str::limit($response->body(), 600, ''),
                ]);

                return [
                    'success' => false,
                    'zernio_post_id' => $zernioPostId,
                    'published' => $published,
                    'error' => $this->firstPlatformError($published)
                        ?? 'Zernio did not return a platform post ID. The post may have been saved as a draft only.',
                ];
            }

            Log::info('ZernioClient::createPost success', [
                'zernio_post_id' => $zernioPostId,
                'platforms' => $published,
            ]);

            return [
                'success' => true,
                'zernio_post_id' => $zernioPostId,
                'published' => $published,
            ];
        } catch (\Throwable $e) {
            Log::error('ZernioClient::createPost exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPost(string $postId): ?array
    {
        try {
            $response = $this->request()->get('/v1/posts/'.rawurlencode($postId));

            if (! $response->successful()) {
                Log::warning('ZernioClient::getPost failed', [
                    'post_id' => $postId,
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 400, ''),
                ]);

                return null;
            }

            $post = $response->json('post');

            return is_array($post) ? $post : null;
        } catch (\Throwable $e) {
            Log::error('ZernioClient::getPost exception', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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

    // ─── Ads API ────────────────────────────────────────────────────────────

    /**
     * Boost an existing Zernio post as a paid ad.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function boostPost(array $payload): array
    {
        return $this->unwrap($this->request()->post('/v1/ads/boost', $payload));
    }

    /**
     * Create a standalone paid ad with custom creative (Meta, Google, TikTok, etc.).
     * Flat body per Zernio docs — platform is inferred from accountId.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createStandaloneAd(array $payload): array
    {
        return $this->unwrap($this->request()->post('/v1/ads/create', $payload));
    }

    /**
     * @deprecated Use createStandaloneAd() — endpoint is POST /v1/ads/create
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createAdCampaign(array $payload): array
    {
        return $this->createStandaloneAd($payload);
    }

    /**
     * Get performance analytics for a specific ad.
     *
     * @return array<string, mixed>
     */
    public function getAdAnalytics(string $adId): array
    {
        return $this->unwrap($this->request()->get("/v1/ads/{$adId}/analytics"));
    }

    /**
     * Get analytics for a campaign (all ads within it).
     *
     * @return array<string, mixed>
     */
    public function getCampaignAnalytics(string $campaignId): array
    {
        return $this->unwrap($this->request()->get("/v1/ad-campaigns/{$campaignId}/analytics"));
    }

    /**
     * Pause a running ad.
     *
     * @return array<string, mixed>
     */
    public function pauseAd(string $adId): array
    {
        return $this->unwrap($this->request()->patch("/v1/ads/{$adId}", ['status' => 'paused']));
    }

    /**
     * Resume a paused ad.
     *
     * @return array<string, mixed>
     */
    public function resumeAd(string $adId): array
    {
        return $this->unwrap($this->request()->patch("/v1/ads/{$adId}", ['status' => 'active']));
    }

    /**
     * List platform ad accounts available for a connected social account.
     *
     * @return list<array<string, mixed>>
     */
    public function listAdAccounts(string $accountId, ?string $adAccountId = null, ?int $limit = null): array
    {
        $query = ['account_id' => $accountId];
        if ($adAccountId !== null && $adAccountId !== '') {
            $query['ad_account_id'] = $adAccountId;
        }
        if ($limit !== null) {
            $query['limit'] = $limit;
        }

        $body = $this->unwrap($this->request()->get('/v1/ads/accounts', $query));

        if (is_array($body['accounts'] ?? null)) {
            return $body['accounts'];
        }

        return is_array($body['data'] ?? null) ? $body['data'] : (is_array($body) ? $body : []);
    }

    /**
     * List Meta pixels / Google conversion actions / LinkedIn rules for a connected ads account.
     *
     * @return list<array<string, mixed>>
     */
    public function listConversionDestinations(string $accountId): array
    {
        $body = $this->unwrap($this->request()->get("/v1/accounts/{$accountId}/conversion-destinations"));

        if (is_array($body['destinations'] ?? null)) {
            return $body['destinations'];
        }

        return is_array($body['data'] ?? null) ? $body['data'] : (is_array($body) ? $body : []);
    }

    /**
     * Create a post (used to get a postId before boosting).
     *
     * @param  array<string, mixed>  $payload  Must contain accountId, platform, text, and optionally mediaUrls, linkUrl.
     * @return array{success: bool, post_id?: string, error?: string}
     */
    public function createAdPost(array $payload): array
    {
        try {
            $response = $this->request()->post('/v1/posts', $payload);

            if (! $response->successful()) {
                return ['success' => false, 'error' => 'Zernio API error ('.$response->status().').'];
            }

            $data    = $response->json('data') ?? $response->json();
            $postId  = is_array($data) ? ($data['postId'] ?? $data['id'] ?? null) : null;

            return ['success' => true, 'post_id' => is_string($postId) ? $postId : null];
        } catch (\Throwable $e) {
            Log::error('ZernioClient::createAdPost exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
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

    /**
     * @param  list<string>  $mediaUrls
     * @return list<array{type: string, url: string}>
     */
    private function buildMediaItems(array $mediaUrls, ?string $mediaType): array
    {
        $items = [];

        foreach ($mediaUrls as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            $items[] = [
                'type' => $mediaType ?? $this->guessMediaTypeFromUrl($url),
                'url' => $url,
            ];
        }

        return $items;
    }

    private function guessMediaTypeFromUrl(string $url): string
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        if (preg_match('/\.(mp4|mov|webm|m4v)$/', $path)) {
            return 'video';
        }

        if (preg_match('/\.gif$/', $path)) {
            return 'gif';
        }

        return 'image';
    }

    private function parseApiErrorMessage(Response $response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            if (is_string($json['error'] ?? null) && $json['error'] !== '') {
                return $json['error'];
            }
            if (is_string($json['message'] ?? null) && $json['message'] !== '') {
                return $json['message'];
            }
        }

        return 'Zernio API error ('.$response->status().').';
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<array{platform: string, external_id: string|null, url: string|null, status: string|null, error: string|null}>
     */
    private function parsePlatformRows(array $rows): array
    {
        $published = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $platform = (string) ($row['platform'] ?? '');
            if ($platform === '') {
                continue;
            }

            $error = $row['errorMessage'] ?? $row['error'] ?? null;

            $published[] = [
                'platform' => $platform,
                'external_id' => is_string($row['platformPostId'] ?? null) ? $row['platformPostId'] : null,
                'url' => is_string($row['platformPostUrl'] ?? null) ? $row['platformPostUrl'] : null,
                'status' => is_string($row['status'] ?? null) ? $row['status'] : null,
                'error' => is_string($error) && $error !== '' ? $error : null,
            ];
        }

        return $published;
    }

    /**
     * @param  list<array{platform: string, external_id: string|null, url: string|null, status: string|null, error?: string|null}>  $published
     * @return list<array{platform: string, external_id: string|null, url: string|null, status: string|null, error?: string|null}>
     */
    private function confirmedPlatformRows(array $published): array
    {
        return array_values(array_filter(
            $published,
            fn (array $row): bool => is_string($row['external_id'] ?? null) && $row['external_id'] !== ''
        ));
    }

    /**
     * @param  list<array{platform: string, external_id: string|null, url: string|null, status: string|null, error?: string|null}>  $published
     */
    private function hasPendingPlatforms(array $published): bool
    {
        foreach ($published as $row) {
            $status = strtolower((string) ($row['status'] ?? ''));
            if (in_array($status, ['publishing', 'pending', 'processing', 'scheduled'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{platform: string, external_id: string|null, url: string|null, status: string|null, error?: string|null}>  $published
     */
    private function firstPlatformError(array $published): ?string
    {
        foreach ($published as $row) {
            $error = $row['error'] ?? null;
            if (is_string($error) && $error !== '') {
                return $error;
            }

            if (($row['status'] ?? null) === 'failed') {
                return 'Publishing failed.';
            }
        }

        return null;
    }

    /**
     * Instagram and some networks finish publishing asynchronously.
     *
     * @param  list<string>  $expectedPlatforms
     * @return array{platforms: list<array{platform: string, external_id: string|null, url: string|null, status: string|null, error: string|null}>, error?: string|null}
     */
    private function waitForPostPlatforms(string $zernioPostId, array $expectedPlatforms): array
    {
        $attempts = (int) config('promotion.zernio.publish_poll_attempts', 15);
        $sleepSeconds = (int) config('promotion.zernio.publish_poll_interval_seconds', 3);
        $expectedPlatforms = array_values(array_filter($expectedPlatforms, fn (string $platform): bool => $platform !== ''));

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if ($attempt > 0 && $sleepSeconds > 0) {
                sleep($sleepSeconds);
            }

            $post = $this->getPost($zernioPostId);
            if (! is_array($post)) {
                continue;
            }

            $platforms = $this->parsePlatformRows($post['platforms'] ?? []);
            if ($expectedPlatforms !== []) {
                $platforms = array_values(array_filter(
                    $platforms,
                    fn (array $row): bool => in_array($row['platform'], $expectedPlatforms, true)
                ));
            }

            if ($this->confirmedPlatformRows($platforms) !== []) {
                return ['platforms' => $platforms];
            }

            $error = $this->firstPlatformError($platforms);
            if ($error !== null) {
                return ['platforms' => $platforms, 'error' => $error];
            }

            if (! $this->hasPendingPlatforms($platforms)) {
                break;
            }
        }

        $finalPost = $this->getPost($zernioPostId);
        $platforms = is_array($finalPost)
            ? $this->parsePlatformRows($finalPost['platforms'] ?? [])
            : [];

        return [
            'platforms' => $platforms,
            'error' => $this->firstPlatformError($platforms),
        ];
    }
}
