<?php

namespace App\Services\Social;

class TwitterTextLimiter
{
    public const URL_WEIGHT = 23;

    public function limit(): int
    {
        return (int) config('promotion.platform_content_limits.twitter', 280);
    }

    /** Target length for AI generation (buffer under hard limit). */
    public function generationTarget(): int
    {
        return min(240, $this->limit() - 40);
    }

    public function sanitize(string $text): string
    {
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/u', '$1 $2', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/u', '$1', $text) ?? $text;
        $text = preg_replace('/\*([^*]+)\*/u', '$1', $text) ?? $text;
        $text = preg_replace('/__([^_]+)__/u', '$1', $text) ?? $text;
        $text = preg_replace('/_([^_]+)_/u', '$1', $text) ?? $text;

        return trim($text);
    }

    public function isPublicHttpUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '[::1]'], true)) {
            return false;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.test') || str_ends_with($host, '.localhost')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return true;
    }

    /** Remove localhost/private URLs that X may reject or break thread chains. */
    public function stripNonPublicUrls(string $text): string
    {
        $text = preg_replace_callback(
            '#https?://[^\s<>"\'\)\]]+#i',
            function (array $matches): string {
                $raw = $matches[0];
                $url = rtrim($raw, '.,;:!?)');
                $suffix = substr($raw, strlen($url));

                return $this->isPublicHttpUrl($url) ? $raw : $suffix;
            },
            $text,
        ) ?? $text;

        return trim(preg_replace('/\s{2,}/u', ' ', $text) ?? $text);
    }

    public function sanitizeForPublish(string $text): string
    {
        return $this->stripNonPublicUrls($this->sanitize($text));
    }

    public function effectiveLength(string $text): int
    {
        $length = 0;
        $offset = 0;

        while (preg_match('#https?://[^\s]+#i', $text, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $match = $matches[0];
            $length += mb_strlen(mb_substr($text, $offset, $match[1] - $offset));
            $length += self::URL_WEIGHT;
            $offset = $match[1] + strlen($match[0]);
        }

        $length += mb_strlen(mb_substr($text, $offset));

        return $length;
    }

    public function clamp(string $text, ?int $maxLength = null): string
    {
        $maxLength ??= $this->limit();
        $text = $this->sanitize($text);

        if ($maxLength <= 0 || $this->effectiveLength($text) <= $maxLength) {
            return $text;
        }

        $low = 1;
        $high = mb_strlen($text);
        $best = '';

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $candidate = $this->truncateAt($text, $mid);
            if ($this->effectiveLength($candidate) <= $maxLength) {
                $best = $candidate;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        if ($best !== '') {
            return $best;
        }

        return $this->truncateAt($text, min($maxLength, mb_strlen($text)));
    }

    /**
     * @param  list<string>  $parts
     * @return list<string>
     */
    public function clampParts(array $parts, ?int $maxLength = null): array
    {
        $maxLength ??= $this->limit();
        $clamped = [];

        foreach ($parts as $part) {
            if (! is_string($part)) {
                continue;
            }

            $text = $this->clamp($part, $maxLength);
            if ($text !== '') {
                $clamped[] = $text;
            }
        }

        return $clamped;
    }

    private function truncateAt(string $text, int $maxLength): string
    {
        $text = trim($text);
        if ($maxLength <= 0 || mb_strlen($text) <= $maxLength) {
            return $text;
        }

        if ($maxLength === 1) {
            return '…';
        }

        $cut = mb_substr($text, 0, $maxLength - 1);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > (int) ($maxLength * 0.6)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " \t\n\r\0\x0B.,;:!?").'…';
    }
}
