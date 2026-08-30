<?php

namespace App\Services\Campaigns;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fetches live marketplace search results via rendered page content.
 *
 * JVZoo and WarriorPlus are Vue/JS apps — plain HTTP returns empty shells.
 * We use Jina Reader (free, renders JS) with ScrapingBee JS as optional fallback.
 */
class MarketplaceHtmlSearchService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchJvzoo(string $keyword): array
    {
        if (! config('services.marketplace.jvzoo_html_enabled', true)) {
            return [];
        }

        $url = 'https://jvzoomarket.com/?s='.urlencode($keyword);
        $content = $this->fetchRenderedContent($url, 'jvzoo');

        if ($content === null) {
            Log::info('[MarketplaceHtmlSearch] jvzoo fetch failed', ['keyword' => $keyword]);

            return [];
        }

        $results = $this->parseJvzooMarkdown($content);

        Log::info('[MarketplaceHtmlSearch] parsed', [
            'marketplace' => 'jvzoo',
            'keyword' => $keyword,
            'count' => count($results),
            'method' => 'jvzoomarket_jina',
        ]);

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchWarriorPlus(string $keyword): array
    {
        if (! config('services.marketplace.warriorplus_html_enabled', true)) {
            return [];
        }

        $url = 'https://warriorplus.com/marketplace/search?q='.urlencode($keyword);
        $content = $this->fetchRenderedContent($url, 'warriorplus');

        if ($content === null) {
            Log::info('[MarketplaceHtmlSearch] warriorplus fetch failed', ['keyword' => $keyword]);

            return [];
        }

        $results = $this->parseWarriorPlusMarkdown($content);

        Log::info('[MarketplaceHtmlSearch] parsed', [
            'marketplace' => 'warriorplus',
            'keyword' => $keyword,
            'count' => count($results),
            'method' => 'marketplace_search_jina',
        ]);

        return $results;
    }

    protected function fetchRenderedContent(string $url, string $marketplace): ?string
    {
        if (! config('services.marketplace.html_scrape_enabled', true)) {
            return null;
        }

        if (config('services.jina_reader.enabled', true)) {
            $jina = $this->fetchViaJina($url);
            if ($this->hasUsableContent($jina, $marketplace)) {
                return $jina;
            }
        }

        $apiKey = trim((string) config('services.scrapingbee.api_key', ''));
        if ($apiKey !== '') {
            $scraped = $this->fetchViaScrapingBee($url, $apiKey);
            if ($this->hasUsableContent($scraped, $marketplace)) {
                return $scraped;
            }
        }

        return null;
    }

    protected function hasUsableContent(?string $content, string $marketplace): bool
    {
        if (! is_string($content) || strlen($content) < 200) {
            return false;
        }

        if (str_contains($content, "We still haven't found what you're looking for")) {
            return false;
        }

        return match ($marketplace) {
            'jvzoo' => str_contains($content, 'jvzoomarket.com/productlibrary/review/'),
            'warriorplus' => str_contains($content, 'warriorplus.com/o/view/'),
            default => true,
        };
    }

    protected function fetchViaJina(string $url): ?string
    {
        try {
            $readerUrl = 'https://r.jina.ai/'.ltrim($url, '/');
            $request = Http::timeout((int) config('services.jina_reader.timeout', 90))
                ->withHeaders([
                    'Accept' => 'text/plain',
                    'X-Return-Format' => 'markdown',
                ]);

            $apiKey = trim((string) config('services.jina_reader.api_key', ''));
            if ($apiKey !== '') {
                $request = $request->withHeaders(['Authorization' => 'Bearer '.$apiKey]);
            }

            $response = $request->get($readerUrl);

            if ($response->successful()) {
                $body = trim($response->body());

                return strlen($body) >= 200 ? $body : null;
            }
        } catch (\Throwable $e) {
            Log::debug('[MarketplaceHtmlSearch] Jina failed', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return null;
    }

    protected function fetchViaScrapingBee(string $url, string $apiKey): ?string
    {
        try {
            $response = Http::timeout(90)->get('https://app.scrapingbee.com/api/v1/', [
                'api_key' => $apiKey,
                'url' => $url,
                'render_js' => 'true',
                'wait' => '5000',
            ]);

            if ($response->successful()) {
                $body = trim($response->body());

                return strlen($body) >= 200 ? $body : null;
            }
        } catch (\Throwable $e) {
            Log::debug('[MarketplaceHtmlSearch] ScrapingBee failed', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseJvzooMarkdown(string $markdown): array
    {
        preg_match_all(
            '#\((https://jvzoomarket\.com/productlibrary/review/\d+[^)]*)\)#i',
            $markdown,
            $urlMatches
        );

        preg_match_all(
            '#Sold By \[[^\]]+\]\([^)]+\)\s*\n+\s*([^\n\[]{10,200})#',
            $markdown,
            $titleMatches
        );

        $urls = array_values(array_unique($urlMatches[1] ?? []));
        $titles = $titleMatches[1] ?? [];

        $results = [];
        $max = (int) config('services.marketplace.max_results', 12);

        foreach (array_slice($urls, 0, $max) as $i => $href) {
            $title = $titles[$i] ?? $this->titleFromJvzooUrl($href);
            $title = $this->cleanTitle($title);

            if ($title === '') {
                continue;
            }

            $results[] = $this->resultRow('jvzoo', $title, $href);
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseWarriorPlusMarkdown(string $markdown): array
    {
        preg_match_all(
            '#\[([^\]]+)\]\((https://warriorplus\.com/o/view/[^)]+)\)#i',
            $markdown,
            $matches,
            PREG_SET_ORDER
        );

        $seen = [];
        $results = [];
        $max = (int) config('services.marketplace.max_results', 12);

        foreach ($matches as $match) {
            $title = $this->cleanTitle($match[1]);
            $href = $match[2];

            if ($title === '' || preg_match('/^\*\*\d+\*\*$/', $title)) {
                continue;
            }

            if (isset($seen[$href])) {
                continue;
            }

            $seen[$href] = true;
            $results[] = $this->resultRow('warriorplus', $title, $href);

            if (count($results) >= $max) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resultRow(string $marketplace, string $title, string $url): array
    {
        return [
            'title' => $title,
            'marketplace' => $marketplace,
            'url' => $url,
            'gravity_hint' => '',
            'epc_hint' => '',
            'why' => 'From live '.ucfirst($marketplace).' marketplace search',
            'source' => $marketplace.'_html',
        ];
    }

    protected function cleanTitle(string $title): string
    {
        $title = trim(strip_tags($title));
        $title = preg_replace('/\s+/', ' ', $title) ?? $title;
        $title = rtrim($title, '.');

        if (strlen($title) < 5 || preg_match('/^\*\*\d+\*\*$/', $title)) {
            return '';
        }

        return Str::limit($title, 120, '…');
    }

    protected function titleFromJvzooUrl(string $url): string
    {
        if (preg_match('#/review/(\d+)#', $url, $m)) {
            return 'JVZoo Product #'.$m[1];
        }

        return '';
    }
}
