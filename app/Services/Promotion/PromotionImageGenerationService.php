<?php

namespace App\Services\Promotion;

use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Services\Cloudinary\CloudinaryService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PromotionImageGenerationService
{
    /**
     * @return array{success: bool, url?: string, prompt: string, error?: string}
     */
    public function generate(Funnel $funnel, FunnelPromotionPost $post): array
    {
        $prompt = $this->buildPrompt($funnel, $post);
        $apiKey = (string) config('services.openai.api_key', '');
        $model  = (string) config('promotion.openai.image_model', 'gpt-image-1');

        if ($apiKey === '') {
            Log::info('[Promotion] PromotionImageGenerationService: no OpenAI key configured — skipping image', [
                'post_id' => $post->id,
            ]);
            return ['success' => true, 'url' => null, 'prompt' => $prompt];
        }

        Log::info('[Promotion] PromotionImageGenerationService: calling OpenAI Images', [
            'post_id' => $post->id,
            'model'   => $model,
            'prompt'  => substr($prompt, 0, 200),
        ]);

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('promotion.openai.timeout', 90))
                ->post('https://api.openai.com/v1/images/generations', [
                    'model'  => $model,
                    'prompt' => $prompt,
                    'n'      => 1,
                    'size'   => '1024x1024',
                ]);

            Log::info('[Promotion] PromotionImageGenerationService: OpenAI response', [
                'post_id'     => $post->id,
                'http_status' => $response->status(),
                'successful'  => $response->successful(),
            ]);

            if (! $response->successful()) {
                $errorBody = $response->body();
                Log::error('[Promotion] PromotionImageGenerationService: OpenAI request failed', [
                    'post_id'     => $post->id,
                    'http_status' => $response->status(),
                    'body'        => substr($errorBody, 0, 500),
                ]);
                return [
                    'success' => false,
                    'prompt'  => $prompt,
                    'error'   => 'Image generation failed: HTTP '.$response->status().' — '.substr($errorBody, 0, 200),
                ];
            }

            $first = $response->json('data.0');

            Log::info('[Promotion] PromotionImageGenerationService: response data keys', [
                'post_id'     => $post->id,
                'has_url'     => ! empty($first['url']),
                'has_b64'     => ! empty($first['b64_json']),
                'data_keys'   => array_keys((array) $first),
            ]);

            if (is_string($first['url'] ?? null) && $first['url'] !== '') {
                Log::info('[Promotion] PromotionImageGenerationService: got direct URL', ['post_id' => $post->id]);
                return ['success' => true, 'url' => $first['url'], 'prompt' => $prompt];
            }

            if (is_string($first['b64_json'] ?? null) && $first['b64_json'] !== '') {
                $binary = base64_decode($first['b64_json'], true);
                if ($binary !== false) {
                    $folder   = 'promotion-assets/' . date('Y/m');
                    $publicId = Str::uuid()->toString();

                    $cloudinary = app(CloudinaryService::class);
                    if ($cloudinary->isConfigured()) {
                        $url = $cloudinary->uploadBinary($binary, $folder, $publicId);
                        if ($url) {
                            Log::info('[Promotion] PromotionImageGenerationService: uploaded to Cloudinary', [
                                'post_id' => $post->id,
                                'url'     => $url,
                            ]);
                            return ['success' => true, 'url' => $url, 'prompt' => $prompt];
                        }
                        Log::warning('[Promotion] Cloudinary upload failed — falling back to local storage.', ['post_id' => $post->id]);
                    }

                    // Fallback: local public disk
                    $path = "{$folder}/{$publicId}.png";
                    Storage::disk('public')->put($path, $binary);
                    $url = Storage::disk('public')->url($path);

                    Log::info('[Promotion] PromotionImageGenerationService: saved b64 image locally', [
                        'post_id' => $post->id,
                        'path'    => $path,
                        'url'     => $url,
                    ]);

                    return ['success' => true, 'url' => $url, 'prompt' => $prompt];
                }

                Log::error('[Promotion] PromotionImageGenerationService: b64 decode failed', ['post_id' => $post->id]);
            }

            Log::error('[Promotion] PromotionImageGenerationService: no image payload in response', [
                'post_id'   => $post->id,
                'data_keys' => array_keys((array) $first),
            ]);

            return ['success' => false, 'prompt' => $prompt, 'error' => 'No image payload returned by OpenAI'];
        } catch (\Throwable $e) {
            Log::error('[Promotion] PromotionImageGenerationService: exception', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'prompt' => $prompt, 'error' => $e->getMessage()];
        }
    }

    private function buildPrompt(Funnel $funnel, FunnelPromotionPost $post): string
    {
        $topic = $post->topic ?: $funnel->name;
        $cta = $post->cta_label ?: 'Learn More';

        return "Create a high-converting promotional social media image for the topic \"{$topic}\". ".
            "Style: clean modern marketing creative, strong focal point, high contrast, no watermarks. ".
            "Include visual space where campaign text could sit. Tone: professional, energetic, outcome-driven. ".
            "CTA theme: {$cta}.";
    }
}
