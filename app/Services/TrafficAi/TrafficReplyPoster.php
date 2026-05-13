<?php

namespace App\Services\TrafficAi;

use App\Models\Mention;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class TrafficReplyPoster
{
    /**
     * @return array{success: bool, external_id?: string|null, error?: string, rate_limited?: bool}
     */
    public function post(SocialAccount $account, Mention $mention, string $replyText): array
    {
        return match ($account->platform) {
            'reddit' => $this->postReddit($account, $mention, $replyText),
            'youtube' => $this->postYouTube($account, $mention, $replyText),
            'twitter' => $this->postX($account, $mention, $replyText),
            default => ['success' => false, 'error' => 'Unsupported platform: '.$account->platform],
        };
    }

    /* ──────────────────────────────────────────────────────────
     | Reddit (OAuth 2 – permanent token)
     ─────────────────────────────────────────────────────────── */

    /**
     * @return array{success: bool, external_id?: string|null, error?: string, rate_limited?: bool}
     */
    private function postReddit(SocialAccount $account, Mention $mention, string $replyText): array
    {
        if (! $account->hasValidAccessToken()) {
            return ['success' => false, 'error' => 'Reddit token missing; reconnect your account.'];
        }

        $postId = $mention->post_id;
        if ($postId === null || $postId === '') {
            return ['success' => false, 'error' => 'Missing Reddit post id.'];
        }

        $thingId = Str::startsWith($postId, 't3_') ? $postId : 't3_'.$postId;
        $userAgent = sprintf(
            '%s:%s:%s (by /u/%s)',
            config('services.reddit.platform', 'web'),
            config('services.reddit.app_id', config('app.name', 'laravel')),
            config('services.reddit.version_string', '1.0'),
            config('services.reddit.bot_username', 'webbrain001')
        );

        try {
            $response = Http::asForm()
                ->withToken($account->access_token)
                ->withHeaders(['User-Agent' => $userAgent])
                ->post('https://oauth.reddit.com/api/comment', [
                    'thing_id' => $thingId,
                    'text' => $replyText,
                ]);

            if ($response->status() === 429) {
                return ['success' => false, 'error' => 'Reddit rate limited.', 'rate_limited' => true];
            }

            if ($response->status() === 403) {
                return ['success' => false, 'error' => 'Reddit auth failed; reconnect your account.'];
            }

            if ($response->successful()) {
                $commentId = $response->json()['json']['data']['things'][0]['data']['id'] ?? null;

                return ['success' => true, 'external_id' => is_string($commentId) ? $commentId : null];
            }

            Log::warning('TrafficReplyPoster[reddit]: error', ['status' => $response->status(), 'body' => Str::limit($response->body(), 400, '')]);

            return ['success' => false, 'error' => 'Reddit API error ('.$response->status().').'];
        } catch (\Throwable $e) {
            Log::error('TrafficReplyPoster[reddit]: exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /* ──────────────────────────────────────────────────────────
     | YouTube Data API v3 – post a top-level comment on a video
     ─────────────────────────────────────────────────────────── */

    /**
     * @return array{success: bool, external_id?: string|null, error?: string, rate_limited?: bool}
     */
    private function postYouTube(SocialAccount $account, Mention $mention, string $replyText): array
    {
        if (! $account->hasValidAccessToken()) {
            return ['success' => false, 'error' => 'YouTube token missing; reconnect your account.'];
        }

        $videoId = $mention->post_id;
        if ($videoId === null || $videoId === '') {
            return ['success' => false, 'error' => 'Missing YouTube video id.'];
        }

        // Refresh token if within 5 min of expiry.
        $account = $this->maybeRefreshGoogleToken($account);
        if ($account === null) {
            return ['success' => false, 'error' => 'Could not refresh YouTube token; reconnect your account.'];
        }

        try {
            $response = Http::withToken($account->access_token)
                ->post('https://www.googleapis.com/youtube/v3/commentThreads?part=snippet', [
                    'snippet' => [
                        'videoId' => $videoId,
                        'topLevelComment' => [
                            'snippet' => [
                                'textOriginal' => $replyText,
                            ],
                        ],
                    ],
                ]);

            if ($response->status() === 429 || $response->status() === 403) {
                $body = $response->json();
                $reason = $body['error']['errors'][0]['reason'] ?? '';

                if (in_array($reason, ['rateLimitExceeded', 'userRateLimitExceeded', 'forbidden'], true)) {
                    return ['success' => false, 'error' => 'YouTube quota/rate limited.', 'rate_limited' => true];
                }

                return ['success' => false, 'error' => 'YouTube auth error; reconnect your account.'];
            }

            if ($response->status() === 401) {
                return ['success' => false, 'error' => 'YouTube token expired; reconnect your account.'];
            }

            if ($response->successful()) {
                $commentId = $response->json()['id'] ?? null;

                return ['success' => true, 'external_id' => is_string($commentId) ? $commentId : null];
            }

            Log::warning('TrafficReplyPoster[youtube]: error', ['status' => $response->status(), 'body' => Str::limit($response->body(), 400, '')]);

            return ['success' => false, 'error' => 'YouTube API error ('.$response->status().').'];
        } catch (\Throwable $e) {
            Log::error('TrafficReplyPoster[youtube]: exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /* ──────────────────────────────────────────────────────────
     | X (Twitter) v2 – reply to a tweet
     ─────────────────────────────────────────────────────────── */

    /**
     * @return array{success: bool, external_id?: string|null, error?: string, rate_limited?: bool}
     */
    private function postX(SocialAccount $account, Mention $mention, string $replyText): array
    {
        if (! $account->hasValidAccessToken()) {
            return ['success' => false, 'error' => 'X token missing; reconnect your account.'];
        }

        $tweetId = $mention->post_id;
        if ($tweetId === null || $tweetId === '') {
            return ['success' => false, 'error' => 'Missing tweet id.'];
        }

        // Refresh token if within 5 min of expiry.
        $account = $this->maybeRefreshXToken($account);
        if ($account === null) {
            return ['success' => false, 'error' => 'Could not refresh X token; reconnect your account.'];
        }

        try {
            $response = Http::withToken($account->access_token)
                ->post('https://api.twitter.com/2/tweets', [
                    'text' => $replyText,
                    'reply' => ['in_reply_to_tweet_id' => $tweetId],
                ]);

            if ($response->status() === 429) {
                return ['success' => false, 'error' => 'X rate limited.', 'rate_limited' => true];
            }

            if (in_array($response->status(), [401, 403], true)) {
                return ['success' => false, 'error' => 'X auth error; reconnect your account.'];
            }

            if ($response->successful()) {
                $newTweetId = $response->json()['data']['id'] ?? null;

                return ['success' => true, 'external_id' => is_string($newTweetId) ? $newTweetId : null];
            }

            Log::warning('TrafficReplyPoster[x]: error', ['status' => $response->status(), 'body' => Str::limit($response->body(), 400, '')]);

            return ['success' => false, 'error' => 'X API error ('.$response->status().').'];
        } catch (\Throwable $e) {
            Log::error('TrafficReplyPoster[x]: exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /* ──────────────────────────────────────────────────────────
     | Token refresh helpers
     ─────────────────────────────────────────────────────────── */

    private function maybeRefreshGoogleToken(SocialAccount $account): ?SocialAccount
    {
        $expiresAt = $account->expires_at;

        if ($expiresAt === null || $expiresAt->isAfter(now()->addMinutes(5))) {
            return $account;
        }

        $refresh = $account->refresh_token;
        if (! is_string($refresh) || $refresh === '') {
            return null;
        }

        try {
            $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
            ]);

            if (! $res->successful()) {
                return null;
            }

            $data = $res->json();
            $newAccess = $data['access_token'] ?? null;
            $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : null;

            if (! is_string($newAccess)) {
                return null;
            }

            $account->forceFill([
                'access_token' => $newAccess,
                'expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
            ])->save();

            return $account;
        } catch (\Throwable $e) {
            Log::warning('TrafficReplyPoster: Google token refresh failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function maybeRefreshXToken(SocialAccount $account): ?SocialAccount
    {
        $expiresAt = $account->expires_at;

        if ($expiresAt === null || $expiresAt->isAfter(now()->addMinutes(5))) {
            return $account;
        }

        $refresh = $account->refresh_token;
        if (! is_string($refresh) || $refresh === '') {
            return null;
        }

        try {
            $basic = base64_encode(config('services.x.client_id').':'.config('services.x.client_secret'));

            $res = Http::asForm()
                ->withHeaders(['Authorization' => 'Basic '.$basic])
                ->post(config('services.x.token_endpoint', 'https://api.twitter.com/2/oauth2/token'), [
                    'refresh_token' => $refresh,
                    'grant_type' => 'refresh_token',
                ]);

            if (! $res->successful()) {
                return null;
            }

            $data = $res->json();
            $newAccess = $data['access_token'] ?? null;
            $newRefresh = $data['refresh_token'] ?? null;
            $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : null;

            if (! is_string($newAccess)) {
                return null;
            }

            $account->forceFill([
                'access_token' => $newAccess,
                'refresh_token' => is_string($newRefresh) ? $newRefresh : $refresh,
                'expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
            ])->save();

            return $account;
        } catch (\Throwable $e) {
            Log::warning('TrafficReplyPoster: X token refresh failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
