<?php

namespace App\Services\DID;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DIDClient
{
    private const BASE_URL = 'https://api.d-id.com';

    private const PRESENTER_CACHE_KEY = 'd-id.presenters.v2';

    private const PRESENTER_CACHE_TTL = 86400; // 24 hours

    /**
     * Curated list of Microsoft Azure Neural voices well-supported by D-ID.
     * Keyed by voice_id (passed directly to D-ID provider).
     */
    public const VOICES = [
        ['id' => 'en-US-JennyNeural',    'name' => 'Jenny',    'lang' => 'English (US)', 'style' => 'Conversational · Female', 'preview_url' => '/audio/voice-previews/en-US-JennyNeural.mp3'],
        ['id' => 'en-US-GuyNeural',      'name' => 'Guy',      'lang' => 'English (US)', 'style' => 'News · Male',           'preview_url' => '/audio/voice-previews/en-US-GuyNeural.mp3'],
        ['id' => 'en-US-AriaNeural',     'name' => 'Aria',     'lang' => 'English (US)', 'style' => 'Friendly · Female',     'preview_url' => '/audio/voice-previews/en-US-AriaNeural.mp3'],
        ['id' => 'en-US-DavisNeural',    'name' => 'Davis',    'lang' => 'English (US)', 'style' => 'Casual · Male',         'preview_url' => '/audio/voice-previews/en-US-DavisNeural.mp3'],
        ['id' => 'en-US-SaraNeural',     'name' => 'Sara',     'lang' => 'English (US)', 'style' => 'Cheerful · Female',     'preview_url' => '/audio/voice-previews/en-US-SaraNeural.mp3'],
        ['id' => 'en-US-TonyNeural',     'name' => 'Tony',     'lang' => 'English (US)', 'style' => 'Authoritative · Male',  'preview_url' => '/audio/voice-previews/en-US-TonyNeural.mp3'],
        ['id' => 'en-GB-SoniaNeural',    'name' => 'Sonia',    'lang' => 'English (UK)', 'style' => 'Professional · Female', 'preview_url' => '/audio/voice-previews/en-GB-SoniaNeural.mp3'],
        ['id' => 'en-GB-RyanNeural',     'name' => 'Ryan',     'lang' => 'English (UK)', 'style' => 'Energetic · Male',      'preview_url' => '/audio/voice-previews/en-GB-RyanNeural.mp3'],
        ['id' => 'en-AU-NatashaNeural',  'name' => 'Natasha',  'lang' => 'English (AU)', 'style' => 'Friendly · Female',     'preview_url' => '/audio/voice-previews/en-AU-NatashaNeural.mp3'],
        ['id' => 'en-AU-WilliamNeural',  'name' => 'William',  'lang' => 'English (AU)', 'style' => 'Warm · Male',           'preview_url' => '/audio/voice-previews/en-AU-WilliamNeural.mp3'],
    ];

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.did.api_key', '');
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== '' && (bool) config('services.did.enabled', false);
    }

    public static function isAllowedVoiceId(string $voiceId): bool
    {
        foreach (self::VOICES as $voice) {
            if (($voice['id'] ?? '') === $voiceId) {
                return true;
            }
        }

        return false;
    }

    // ─── Presenters ──────────────────────────────────────────────────────────

    /**
     * Fetch and cache D-ID stock presenters. Each item has:
     *   id, name, preview_url, thumbnail_url, owner, talking_preview_url
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPresenters(): array
    {
        $cached = Cache::get(self::PRESENTER_CACHE_KEY);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        Log::info('[D-ID] Fetching presenters from API.');

        $response = $this->http()->get('/clips/presenters');

        if (! $response->successful()) {
            Log::warning('[D-ID] Failed to fetch presenters', ['status' => $response->status(), 'body' => $response->body()]);

            return is_array($cached) ? $cached : [];
        }

        $presenters = $response->json('presenters', []);
        if (! is_array($presenters)) {
            $presenters = [];
        }

        if ($presenters !== []) {
            Cache::put(self::PRESENTER_CACHE_KEY, $presenters, self::PRESENTER_CACHE_TTL);
        }

        Log::info('[D-ID] Cached '.count($presenters).' presenters.');

        return $presenters;
    }

    /**
     * Force-refresh the presenter cache.
     */
    public function refreshPresenters(): array
    {
        Cache::forget(self::PRESENTER_CACHE_KEY);

        return $this->getPresenters();
    }

    // ─── Clip (V3 Pro presenter video) ───────────────────────────────────────

    /**
     * Create a clip using a D-ID stock presenter (from /clips/presenters).
     *
     * @return array{id?: string, status?: string, error?: string}
     */
    public function createClip(string $presenterId, string $script, string $voiceId = 'en-US-JennyNeural'): array
    {
        Log::info('[D-ID] Creating clip', ['presenter_id' => $presenterId, 'voice' => $voiceId, 'script_len' => strlen($script)]);

        $response = $this->http()->post('/clips', [
            'presenter_id' => $presenterId,
            'script' => [
                'type'     => 'text',
                'input'    => $script,
                'provider' => [
                    'type'     => 'microsoft',
                    'voice_id' => $voiceId,
                ],
            ],
            'config' => [
                'result_format' => 'mp4',
            ],
        ]);

        if (! $response->successful()) {
            Log::error('[D-ID] createClip failed', ['status' => $response->status(), 'body' => $response->body()]);

            return ['error' => 'D-ID API error '.$response->status().': '.$response->body()];
        }

        $data = $response->json();
        Log::info('[D-ID] Clip created', ['id' => $data['id'] ?? null, 'status' => $data['status'] ?? null]);

        return is_array($data) ? $data : [];
    }

    /**
     * Poll the status of a clip.
     *
     * @return array{id?: string, status?: string, result_url?: string, error?: string}
     */
    public function getClip(string $clipId): array
    {
        $response = $this->http()->get('/clips/'.$clipId);

        if (! $response->successful()) {
            Log::warning('[D-ID] getClip failed', ['id' => $clipId, 'status' => $response->status()]);

            return ['error' => 'D-ID API error '.$response->status()];
        }

        return $response->json() ?? [];
    }

    // ─── Talk (photo-based video) ─────────────────────────────────────────────

    /**
     * Create a new talk (async video generation job).
     *
     * @return array{id?: string, status?: string, error?: string}
     */
    public function createTalk(string $presenterImageUrl, string $script, string $voiceId = 'en-US-JennyNeural'): array
    {
        Log::info('[D-ID] Creating talk', ['presenter' => $presenterImageUrl, 'voice' => $voiceId, 'script_len' => strlen($script)]);

        $response = $this->http()->post('/talks', [
            'source_url' => $presenterImageUrl,
            'script' => [
                'type'     => 'text',
                'input'    => $script,
                'provider' => [
                    'type'     => 'microsoft',
                    'voice_id' => $voiceId,
                ],
            ],
            'config' => [
                'stitch'        => true,
                'result_format' => 'mp4',
            ],
        ]);

        if (! $response->successful()) {
            Log::error('[D-ID] createTalk failed', ['status' => $response->status(), 'body' => $response->body()]);

            return ['error' => 'D-ID API error '.$response->status().': '.$response->body()];
        }

        $data = $response->json();
        Log::info('[D-ID] Talk created', ['id' => $data['id'] ?? null, 'status' => $data['status'] ?? null]);

        return is_array($data) ? $data : [];
    }

    /**
     * Poll the status of a talk.
     *
     * @return array{id?: string, status?: string, result_url?: string, error?: string}
     */
    public function getTalk(string $talkId): array
    {
        $response = $this->http()->get('/talks/'.$talkId);

        if (! $response->successful()) {
            Log::warning('[D-ID] getTalk failed', ['id' => $talkId, 'status' => $response->status()]);

            return ['error' => 'D-ID API error '.$response->status()];
        }

        return $response->json() ?? [];
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    private function http(): PendingRequest
    {
        // D-ID keys are `API_USERNAME:API_PASSWORD` — encode as standard HTTP Basic auth.
        return Http::withHeaders([
            'Authorization' => 'Basic '.base64_encode($this->apiKey),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])
            ->baseUrl(self::BASE_URL)
            ->timeout((int) config('services.did.timeout', 120));
    }
}
