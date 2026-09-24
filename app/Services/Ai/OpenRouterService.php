<?php

namespace App\Services\Ai;

use App\Services\Content\PlatformFormatCatalog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    public function isConfigured(): bool
    {
        return (string) config('services.openrouter.api_key', '') !== '';
    }

    public function modelFor(string $key): string
    {
        $configured = config("services.openrouter.models.{$key}");

        $model = is_string($configured) && $configured !== ''
            ? $configured
            : (string) config('services.openrouter.model', 'openai/gpt-4o-mini');

        return $this->resolveModel($model);
    }

    public function promotionTextModel(): string
    {
        return $this->resolveModel((string) config('promotion.openrouter.text_model', $this->modelFor('promotion_text')));
    }

    public function promotionImageModel(): string
    {
        return $this->resolveModel((string) config('promotion.openrouter.image_model', $this->modelFor('promotion_image')));
    }

    public function resolveModel(string $model): string
    {
        $model = trim($model);
        if ($model === '') {
            return 'openai/gpt-4o-mini';
        }

        if (str_contains($model, '/')) {
            return $model;
        }

        return 'openai/'.$model;
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'HTTP-Referer' => (string) config('app.url'),
            'X-Title' => (string) config('app.name', 'AffiliateOS AI'),
        ];
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{ok: bool, content: string|null, error: string|null, raw?: mixed}
     */
    public function chat(
        array $messages,
        ?string $model = null,
        float $temperature = 0.4,
        ?int $timeout = null,
        ?int $maxTokens = null,
        ?array $responseFormat = null,
    ): array {
        $apiKey = (string) config('services.openrouter.api_key', '');
        if ($apiKey === '') {
            return [
                'ok' => false,
                'content' => null,
                'error' => 'OPENROUTER_API_KEY is not configured.',
            ];
        }

        $model = $this->resolveModel($model ?: (string) config('services.openrouter.model', 'openai/gpt-4o-mini'));
        $baseUrl = $this->baseUrl();
        $timeout ??= (int) config('services.openrouter.timeout', 90);
        $connectTimeout = (int) config('services.openrouter.connect_timeout', 15);
        $retries = (int) config('services.openrouter.retries', 2);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->retry($retries, 750, function (\Throwable $e): bool {
                    return $e instanceof ConnectionException
                        || str_contains(strtolower($e->getMessage()), 'connection was reset')
                        || str_contains(strtolower($e->getMessage()), 'timed out');
                }, throw: false)
                ->withHeaders($this->requestHeaders())
                ->post($baseUrl.'/chat/completions', array_filter([
                    'model' => $model,
                    'temperature' => $temperature,
                    'messages' => $messages,
                    'max_tokens' => $maxTokens,
                    'response_format' => $responseFormat,
                ], fn ($v) => $v !== null));

            if (! $response->successful()) {
                Log::warning('[OpenRouter] chat failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'model' => $model,
                ]);

                return [
                    'ok' => false,
                    'content' => null,
                    'error' => 'OpenRouter request failed ('.$response->status().').',
                    'raw' => $response->json(),
                ];
            }

            $content = data_get($response->json(), 'choices.0.message.content');

            return [
                'ok' => is_string($content) && trim($content) !== '',
                'content' => is_string($content) ? trim($content) : null,
                'error' => is_string($content) && trim($content) !== '' ? null : 'Empty model response.',
                'raw' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::warning('[OpenRouter] exception', ['message' => $e->getMessage()]);

            return [
                'ok' => false,
                'content' => null,
                'error' => 'Could not reach OpenRouter: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Generate an image via OpenRouter's Image API (e.g. openai/gpt-image-1).
     *
     * @param  array<string, mixed>  $options
     * @return array{ok: bool, url: string|null, b64_json: string|null, error: string|null, raw?: mixed}
     */
    public function generateImage(string $prompt, ?string $model = null, array $options = []): array
    {
        $apiKey = (string) config('services.openrouter.api_key', '');
        if ($apiKey === '') {
            return [
                'ok' => false,
                'url' => null,
                'b64_json' => null,
                'error' => 'OPENROUTER_API_KEY is not configured.',
            ];
        }

        $model = $this->resolveModel($model ?: $this->promotionImageModel());
        $timeout = (int) ($options['timeout'] ?? config('promotion.openrouter.timeout', 90));
        $connectTimeout = (int) config('services.openrouter.connect_timeout', 15);
        $retries = (int) config('services.openrouter.retries', 2);

        $size = isset($options['size']) && is_string($options['size'])
            ? $this->normalizeImageSize($options['size'])
            : null;

        $payload = array_filter([
            'model' => $model,
            'prompt' => $prompt,
            'n' => (int) ($options['n'] ?? 1),
            'size' => $size,
            'aspect_ratio' => $options['aspect_ratio'] ?? null,
            'quality' => $options['quality'] ?? null,
            'output_format' => $options['output_format'] ?? 'png',
            'background' => $options['background'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->retry($retries, 750, function (\Throwable $e): bool {
                    return $e instanceof ConnectionException
                        || str_contains(strtolower($e->getMessage()), 'connection was reset')
                        || str_contains(strtolower($e->getMessage()), 'timed out');
                }, throw: false)
                ->withHeaders($this->requestHeaders())
                ->post($this->baseUrl().'/images', $payload);

            if (! $response->successful()) {
                Log::error('[OpenRouter] image generation failed', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                    'model' => $model,
                ]);

                return [
                    'ok' => false,
                    'url' => null,
                    'b64_json' => null,
                    'error' => 'Image generation failed: HTTP '.$response->status().' — '.substr($response->body(), 0, 200),
                    'raw' => $response->json(),
                ];
            }

            $first = (array) ($response->json('data.0') ?? []);
            $url = is_string($first['url'] ?? null) ? $first['url'] : null;
            $b64 = is_string($first['b64_json'] ?? null) ? $first['b64_json'] : null;

            if (($url === null || $url === '') && ($b64 === null || $b64 === '')) {
                return [
                    'ok' => false,
                    'url' => null,
                    'b64_json' => null,
                    'error' => 'No image payload returned by OpenRouter.',
                    'raw' => $response->json(),
                ];
            }

            return [
                'ok' => true,
                'url' => $url,
                'b64_json' => $b64,
                'error' => null,
                'raw' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::error('[OpenRouter] image exception', ['message' => $e->getMessage()]);

            return [
                'ok' => false,
                'url' => null,
                'b64_json' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ask the model for JSON and decode it.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{ok: bool, data: array<string, mixed>|null, error: string|null}
     */
    public function chatJson(
        array $messages,
        ?string $model = null,
        ?int $timeout = null,
        ?int $maxTokens = null,
        float $temperature = 0.2,
        bool $strictJsonObject = false,
    ): array {
        $responseFormat = $strictJsonObject ? ['type' => 'json_object'] : null;
        $result = $this->chat($messages, $model, $temperature, $timeout, $maxTokens, $responseFormat);
        if (! $result['ok'] || ! is_string($result['content'])) {
            return ['ok' => false, 'data' => null, 'error' => $result['error'] ?? 'No content'];
        }

        $decoded = $this->decodeJsonFromText($result['content']);
        if (! is_array($decoded)) {
            Log::warning('[OpenRouter] JSON decode failed', [
                'snippet' => mb_substr($result['content'], 0, 400),
            ]);

            return ['ok' => false, 'data' => null, 'error' => 'Model did not return valid JSON.'];
        }

        return ['ok' => true, 'data' => $decoded, 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeJsonFromText(string $text): ?array
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $m)) {
            $text = trim($m[1]);
        }

        foreach ($this->jsonCandidates($text) as $candidate) {
            $decoded = json_decode($candidate, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function jsonCandidates(string $text): array
    {
        $candidates = [$text];

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($text, $start, $end - $start + 1);
        }

        $repaired = preg_replace('/,\s*([\]}])/', '$1', $text) ?? $text;
        $candidates[] = $repaired;

        if ($start !== false && $end !== false && $end > $start) {
            $slice = substr($text, $start, $end - $start + 1);
            $candidates[] = preg_replace('/,\s*([\]}])/', '$1', $slice) ?? $slice;
        }

        // Salvage truncated responses that end mid-array/object.
        if ($start !== false) {
            $tail = substr($text, $start);
            $candidates[] = $this->closeTruncatedJson($tail);
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    protected function closeTruncatedJson(string $json): string
    {
        $json = rtrim($json);
        $json = preg_replace('/,\s*$/', '', $json) ?? $json;

        $inString = false;
        $escaped = false;
        $stack = [];

        $len = strlen($json);
        for ($i = 0; $i < $len; $i++) {
            $char = $json[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;

                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{' || $char === '[') {
                $stack[] = $char;
            } elseif ($char === '}' && ($stack[count($stack) - 1] ?? null) === '{') {
                array_pop($stack);
            } elseif ($char === ']' && ($stack[count($stack) - 1] ?? null) === '[') {
                array_pop($stack);
            }
        }

        if ($inString) {
            $json .= '"';
        }

        while ($stack !== []) {
            $open = array_pop($stack);
            $json .= $open === '{' ? '}' : ']';
        }

        return $json;
    }

    /**
     * Try primary model, then optional free/cheap fallback.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{ok: bool, data: array<string, mixed>|null, error: string|null, model_used?: string}
     */
    public function chatJsonWithFallback(array $messages, string $modelKey, ?string $fallbackKey = 'free_fallback', ?int $timeout = null, ?int $maxTokens = null): array
    {
        $primary = $this->modelFor($modelKey);
        $result = $this->chatJson($messages, $primary, $timeout, $maxTokens);

        if ($result['ok']) {
            return [...$result, 'model_used' => $primary];
        }

        if ($fallbackKey === null) {
            return $result;
        }

        $fallback = $this->modelFor($fallbackKey);
        if ($fallback === $primary) {
            return $result;
        }

        $retry = $this->chatJson($messages, $fallback, $timeout, $maxTokens);

        if ($retry['ok']) {
            return [...$retry, 'model_used' => $fallback];
        }

        return ['ok' => false, 'data' => null, 'error' => $retry['error'] ?? $result['error']];
    }

    public function leadMagnetTimeout(): int
    {
        return (int) config('services.openrouter.lead_magnet_timeout', 180);
    }

    private function normalizeImageSize(string $size): string
    {
        if (in_array($size, ['1024x1024', '1024x1536', '1536x1024', 'auto'], true)) {
            return $size;
        }

        return app(PlatformFormatCatalog::class)->normalizeOpenRouterImageSize($size);
    }
}
