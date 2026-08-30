<?php

namespace App\Services\Campaigns;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches readable text from sales/JV pages using a free-first fallback chain.
 *
 * Order: Jina Reader (free) → direct HTTP → ScrapingBee (paid, if configured).
 */
class SalesPageFetcherService
{
    public const MIN_TEXT_LENGTH = 40;

    public function fetch(string $url): array
    {
        $url = trim($url);
        $attempts = [];

        if (config('services.jina_reader.enabled', true)) {
            $text = $this->fetchViaJina($url);
            $attempts[] = ['method' => 'jina', 'length' => $text ? strlen($text) : 0];
            if ($this->isUsable($text)) {
                return $this->success('jina', $text);
            }
        }

        $text = $this->fetchDirect($url);
        $attempts[] = ['method' => 'direct', 'length' => $text ? strlen($text) : 0];
        if ($this->isUsable($text)) {
            return $this->success('direct', $text);
        }

        $apiKey = trim((string) config('services.scrapingbee.api_key', ''));
        if ($apiKey !== '') {
            $text = $this->fetchViaScrapingBee($url, $apiKey, renderJs: false);
            $attempts[] = ['method' => 'scrapingbee', 'length' => $text ? strlen($text) : 0];
            if ($this->isUsable($text)) {
                return $this->success('scrapingbee', $text);
            }

            $text = $this->fetchViaScrapingBee($url, $apiKey, renderJs: true);
            $attempts[] = ['method' => 'scrapingbee_js', 'length' => $text ? strlen($text) : 0];
            if ($this->isUsable($text)) {
                return $this->success('scrapingbee_js', $text);
            }
        }

        Log::info('[SalesPageFetcher] all methods exhausted', [
            'url' => $url,
            'attempts' => $attempts,
        ]);

        return [
            'ok' => false,
            'text' => null,
            'method' => null,
            'attempts' => $attempts,
        ];
    }

    protected function isUsable(?string $text): bool
    {
        return is_string($text) && strlen(trim($text)) >= self::MIN_TEXT_LENGTH;
    }

    /**
     * @return array{ok: true, text: string, method: string, attempts: array<int, array<string, mixed>>}
     */
    protected function success(string $method, string $text): array
    {
        return [
            'ok' => true,
            'text' => trim($text),
            'method' => $method,
            'attempts' => [],
        ];
    }

    /**
     * Jina Reader — free URL-to-markdown (no key required; optional JINA_API_KEY for higher limits).
     *
     * @see https://jina.ai/reader/
     */
    protected function fetchViaJina(string $url): ?string
    {
        try {
            $readerUrl = 'https://r.jina.ai/'.ltrim($url, '/');

            $request = Http::timeout((int) config('services.jina_reader.timeout', 60))
                ->withHeaders(array_filter([
                    'Accept' => 'text/plain',
                    'X-Return-Format' => 'markdown',
                    'Authorization' => filled(config('services.jina_reader.api_key'))
                        ? 'Bearer '.config('services.jina_reader.api_key')
                        : null,
                ]));

            $response = $request->get($readerUrl);

            if (! $response->successful()) {
                return null;
            }

            $body = trim((string) $response->body());

            return $body !== '' ? $body : null;
        } catch (\Throwable $e) {
            Log::debug('[SalesPageFetcher] Jina failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    protected function fetchDirect(string $url): ?string
    {
        try {
            $response = Http::timeout((int) config('services.sales_page_fetcher.direct_timeout', 30))
                ->withOptions(['allow_redirects' => true])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            return $this->htmlToText((string) $response->body());
        } catch (\Throwable $e) {
            Log::debug('[SalesPageFetcher] direct fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    protected function fetchViaScrapingBee(string $url, string $apiKey, bool $renderJs): ?string
    {
        try {
            $params = [
                'api_key' => $apiKey,
                'url' => $url,
                'render_js' => $renderJs ? 'true' : 'false',
            ];

            if ($renderJs) {
                $params['wait_browser'] = 'networkidle2';
                $params['block_resources'] = 'false';
            }

            $response = Http::timeout($renderJs ? 90 : 45)
                ->get('https://app.scrapingbee.com/api/v1/', $params);

            if (! $response->successful()) {
                return null;
            }

            return $this->htmlToText((string) $response->body());
        } catch (\Throwable $e) {
            Log::debug('[SalesPageFetcher] ScrapingBee failed', [
                'url' => $url,
                'render_js' => $renderJs,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function htmlToText(string $html): ?string
    {
        $html = preg_replace('/<(script|style|noscript)[^>]*>[\s\S]*?<\/\1>/i', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html));
        $text = preg_replace('/\s+/', ' ', $text) ?? '';

        $text = trim($text);

        return $text !== '' ? $text : null;
    }
}
