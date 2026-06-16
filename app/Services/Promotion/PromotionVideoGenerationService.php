<?php

namespace App\Services\Promotion;

use App\Models\Funnel;
use App\Models\FunnelPromotionAsset;
use App\Models\FunnelPromotionPost;
use App\Services\DID\DIDClient;
use Illuminate\Support\Facades\Log;

class PromotionVideoGenerationService
{
    public function __construct(private readonly DIDClient $did) {}

    /**
     * @return array{success: bool, script: string, remote_id?: string, status?: string, video_url?: string|null, thumbnail_url?: string|null, duration_seconds?: int|null, error?: string}
     */
    public function generate(Funnel $funnel, FunnelPromotionPost $post): array
    {
        $script = $this->buildScript($funnel, $post);

        if (! $this->did->isEnabled()) {
            Log::info('[D-ID] Video generation disabled – returning script-only result.', ['post_id' => $post->id]);

            return [
                'success'          => true,
                'script'           => $script,
                'status'           => 'ready',
                'video_url'        => null,
                'thumbnail_url'    => null,
                'duration_seconds' => null,
            ];
        }

        try {
            $ctx         = is_array($post->generation_context) ? $post->generation_context : [];
            $voiceId     = (string) ($ctx['voice_id'] ?? config('services.did.default_voice_id', 'en-US-JennyNeural'));
            $presenterId = $this->resolvePresenterId((string) ($ctx['avatar_id'] ?? ''));

            Log::info('[D-ID] Starting video generation', [
                'post_id'       => $post->id,
                'voice_id'      => $voiceId,
                'presenter_id'  => $presenterId,
                'script_chars'  => strlen($script),
            ]);

            $clipData = $this->did->createClip($presenterId, $script, $voiceId);

            if (isset($clipData['error'])) {
                return ['success' => false, 'script' => $script, 'error' => $clipData['error']];
            }

            $clipId = (string) ($clipData['id'] ?? '');
            if ($clipId === '') {
                return ['success' => false, 'script' => $script, 'error' => 'D-ID did not return a clip ID.'];
            }

            $poll   = $this->did->getClip($clipId);
            $status = (string) ($poll['status'] ?? 'created');

            Log::info('[D-ID] Clip polled', ['clip_id' => $clipId, 'status' => $status]);

            $videoUrl     = isset($poll['result_url']) && is_string($poll['result_url']) ? $poll['result_url'] : null;
            $thumbnailUrl = isset($poll['thumbnail_url']) && is_string($poll['thumbnail_url']) ? $poll['thumbnail_url'] : null;

            return [
                'success'          => true,
                'script'           => $script,
                'remote_id'        => $clipId,
                'status'           => $status === 'done' ? 'ready' : 'processing',
                'video_url'        => $videoUrl,
                'thumbnail_url'    => $thumbnailUrl,
                'duration_seconds' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('[D-ID] Exception during video generation', ['post_id' => $post->id, 'error' => $e->getMessage()]);

            return ['success' => false, 'script' => $script, 'error' => $e->getMessage()];
        }
    }

    /**
     * Poll a D-ID clip until it is ready, still processing, or failed.
     *
     * @return array{state: 'ready'|'processing'|'failed', video_url?: string|null, thumbnail_url?: string|null, error?: string, remote_status?: string}
     */
    public function pollClip(string $clipId): array
    {
        $poll = $this->did->getClip($clipId);

        if (isset($poll['error']) && is_string($poll['error'])) {
            return ['state' => 'failed', 'error' => $poll['error']];
        }

        $status = strtolower((string) ($poll['status'] ?? ''));
        $videoUrl = isset($poll['result_url']) && is_string($poll['result_url']) ? $poll['result_url'] : null;
        $thumbnailUrl = isset($poll['thumbnail_url']) && is_string($poll['thumbnail_url']) ? $poll['thumbnail_url'] : null;

        if (in_array($status, ['error', 'failed', 'rejected'], true)) {
            $error = $poll['error'] ?? 'D-ID clip generation failed';
            if (is_array($error)) {
                $error = (string) ($error['description'] ?? $error['kind'] ?? json_encode($error));
            }

            return [
                'state'          => 'failed',
                'error'          => (string) $error,
                'remote_status'  => $status,
            ];
        }

        if ($status === 'done' || $videoUrl !== null) {
            return [
                'state'          => 'ready',
                'video_url'      => $videoUrl,
                'thumbnail_url'  => $thumbnailUrl,
                'remote_status'  => $status !== '' ? $status : 'done',
            ];
        }

        return [
            'state'         => 'processing',
            'remote_status' => $status !== '' ? $status : 'processing',
        ];
    }

    /**
     * Apply a poll result to the post's video asset and post status.
     *
     * @param  array{state: string, video_url?: string|null, thumbnail_url?: string|null, error?: string, remote_status?: string}  $poll
     */
    public function applyClipPollResult(FunnelPromotionPost $post, FunnelPromotionAsset $videoAsset, array $poll): void
    {
        $state = (string) ($poll['state'] ?? 'processing');

        if ($state === 'ready') {
            $scriptAsset = $post->assets()
                ->where('asset_type', FunnelPromotionAsset::TYPE_SCRIPT)
                ->first();

            $videoAsset->update([
                'status'        => FunnelPromotionAsset::STATUS_READY,
                'url'           => $poll['video_url'] ?? $videoAsset->url,
                'thumbnail_url' => $poll['thumbnail_url'] ?? $videoAsset->thumbnail_url,
                'meta'          => array_merge((array) ($videoAsset->meta ?? []), [
                    'status'        => $poll['remote_status'] ?? 'done',
                    'polled_at'     => now()->toIso8601String(),
                ]),
            ]);

            $post->update([
                'primary_asset_id' => $videoAsset->id,
                'status'           => FunnelPromotionPost::STATUS_READY,
                'last_error'       => null,
                'text_body'        => $post->text_body ?: $scriptAsset?->source_prompt,
            ]);

            Log::info('[D-ID] Clip ready', [
                'post_id'       => $post->id,
                'clip_id'       => $videoAsset->remote_id,
                'video_url'     => $videoAsset->url,
                'has_thumbnail' => $videoAsset->thumbnail_url !== null,
            ]);

            return;
        }

        if ($state === 'failed') {
            $error = (string) ($poll['error'] ?? 'Video generation failed');

            $videoAsset->update([
                'status' => FunnelPromotionAsset::STATUS_FAILED,
                'meta'   => array_merge((array) ($videoAsset->meta ?? []), [
                    'status'    => $poll['remote_status'] ?? 'failed',
                    'error'     => $error,
                    'polled_at' => now()->toIso8601String(),
                ]),
            ]);

            $post->update([
                'status'     => FunnelPromotionPost::STATUS_FAILED,
                'last_error' => $error,
            ]);

            Log::warning('[D-ID] Clip failed', ['post_id' => $post->id, 'clip_id' => $videoAsset->remote_id, 'error' => $error]);

            return;
        }

        $videoAsset->update([
            'meta' => array_merge((array) ($videoAsset->meta ?? []), [
                'status'    => $poll['remote_status'] ?? 'processing',
                'polled_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function resolvePresenterId(string $avatarId): string
    {
        if ($avatarId !== '') {
            $presenters = $this->did->getPresenters();
            foreach ($presenters as $p) {
                if (is_array($p) && (($p['presenter_id'] ?? '') === $avatarId)) {
                    return (string) $p['presenter_id'];
                }
            }

            // Allow passing a raw presenter_id even if not in cache.
            if (str_starts_with($avatarId, 'v2_public_') || str_contains($avatarId, '@')) {
                return $avatarId;
            }
        }

        $default = (string) config('services.did.default_presenter_id', '');
        if ($default !== '') {
            return $default;
        }

        $presenters = $this->did->getPresenters();
        if ($presenters !== [] && is_array($presenters[0])) {
            return (string) ($presenters[0]['presenter_id'] ?? '');
        }

        return 'v2_public_Adam@0GLJgELXjc';
    }

    private function buildScript(Funnel $funnel, FunnelPromotionPost $post): string
    {
        $body = $post->text_body ?? '';

        // If we have a generated text body, strip markdown and trim to a 60-second spoken limit (~800 chars).
        if ($body !== '') {
            $spoken = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $body) ?? $body;
            $spoken = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $spoken) ?? $spoken;
            $spoken = trim((string) $spoken);

            return mb_substr($spoken, 0, 800);
        }

        // Fallback: build a short script from the topic + CTA.
        $topic    = $post->topic ?: $funnel->name;
        $ctaLabel = $post->cta_label ?: 'Learn more';
        $ctaUrl   = $post->cta_url  ?: '';

        return implode(' ', [
            "If you are struggling with {$topic}, this is for you.",
            'In the next minute, I will show you one clear strategy you can apply immediately.',
            'Use this framework to improve your results, avoid common mistakes, and move faster with confidence.',
            "When you are ready, click {$ctaLabel}".($ctaUrl !== '' ? " at {$ctaUrl}" : '.'),
        ]);
    }
}
