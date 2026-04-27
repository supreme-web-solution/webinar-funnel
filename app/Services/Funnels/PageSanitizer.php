<?php

namespace App\Services\Funnels;

class PageSanitizer
{
    /**
     * Strip potentially dangerous fields/scripts from GrapesJS payload.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function sanitize(array $schema): array
    {
        $sanitized = $this->walk($schema);

        if (isset($sanitized['scripts'])) {
            unset($sanitized['scripts']);
        }

        return $sanitized;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function walk(mixed $value): mixed
    {
        if (! is_array($value)) {
            if (! is_string($value)) {
                return $value;
            }

            return $this->sanitizeGenericString($value);
        }

        $result = [];

        foreach ($value as $key => $item) {
            $normalizedKey = is_string($key) ? strtolower($key) : '';

            if ($normalizedKey !== '' && str_contains($normalizedKey, 'script')) {
                continue;
            }

            if (is_string($item)) {
                if ($normalizedKey === 'html') {
                    $result[$key] = $this->sanitizeHtml($item);
                    continue;
                }

                if ($normalizedKey === 'css' || $normalizedKey === 'style') {
                    $result[$key] = $this->sanitizeCss($item);
                    continue;
                }

                $result[$key] = $this->sanitizeGenericString($item);
                continue;
            }

            $result[$key] = $this->walk($item);
        }

        return $result;
    }

    private function sanitizeGenericString(string $input): string
    {
        $sanitized = preg_replace('/<\s*script\b[^>]*>(.*?)<\s*\/\s*script>/is', '', $input) ?? $input;
        $sanitized = preg_replace('/\son[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace(
            '/\s(?:href|src|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*\1/is',
            '',
            $sanitized
        ) ?? $sanitized;
        $sanitized = preg_replace(
            '/\s(?:href|src|xlink:href)\s*=\s*javascript:[^\s>]+/is',
            '',
            $sanitized
        ) ?? $sanitized;

        return $sanitized;
    }

    private function sanitizeHtml(string $html): string
    {
        $sanitized = $this->sanitizeGenericString($html);

        // Remove inline event handlers such as onclick=, onerror=, onload=.
        $sanitized = preg_replace('/\son[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $sanitized) ?? $sanitized;

        // Block javascript: URLs in href/src/xlink:href attributes.
        $sanitized = preg_replace(
            '/\s(?:href|src|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*\1/is',
            '',
            $sanitized
        ) ?? $sanitized;
        $sanitized = preg_replace(
            '/\s(?:href|src|xlink:href)\s*=\s*javascript:[^\s>]+/is',
            '',
            $sanitized
        ) ?? $sanitized;

        return $sanitized;
    }

    private function sanitizeCss(string $css): string
    {
        $sanitized = $css;

        // Disallow legacy CSS execution vectors.
        $sanitized = preg_replace('/expression\s*\((.*?)\)/is', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/behavior\s*:\s*url\s*\((.*?)\)/is', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/@import\s+url\s*\(\s*["\']?\s*javascript:[^)]+\)/is', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/url\s*\(\s*["\']?\s*javascript:[^)]+\)/is', '', $sanitized) ?? $sanitized;

        return $sanitized;
    }
}
