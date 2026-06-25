<?php

namespace App\Services\Funnels;

class TemplateAiKnowledgeFormatter
{
    /**
     * @param  array<string, mixed>  $offer
     */
    public function format(array $offer, ?string $jvExcerpt = null): string
    {
        $name = trim((string) ($offer['name'] ?? 'Product'));
        $productName = (string) preg_replace('/\s+Offer$/i', '', $name);
        $intro = trim((string) ($offer['optin_intro'] ?? ''));
        $bullets = array_values(array_filter(
            (array) ($offer['bullet_points'] ?? []),
            fn ($b): bool => is_string($b) && trim($b) !== ''
        ));
        $jvPage = trim((string) ($offer['jv_page'] ?? ''));
        $affiliateLink = trim((string) ($offer['affiliate_request_link'] ?? ''));
        $webinarTitle = trim((string) ($offer['webinar_title'] ?? ''));

        $lines = [
            "# {$productName} — Webinar AI Knowledge Base",
            '',
            '## Product Overview',
            $intro !== '' ? $intro : "{$productName} is a software/training offer promoted through this webinar funnel.",
            '',
        ];

        if ($webinarTitle !== '') {
            $lines[] = '## Webinar Training Focus';
            $lines[] = $webinarTitle;
            $lines[] = '';
        }

        if ($bullets !== []) {
            $lines[] = '## Key Benefits & Talking Points';
            foreach ($bullets as $bullet) {
                $lines[] = '- '.trim($bullet);
            }
            $lines[] = '';
        }

        if ($jvExcerpt !== null && trim($jvExcerpt) !== '') {
            $lines[] = '## Additional Details from JV / Partner Page';
            $lines[] = trim($jvExcerpt);
            $lines[] = '';
        }

        $lines[] = '## Important Links';
        if ($jvPage !== '') {
            $lines[] = "- Sales / JV page: {$jvPage}";
        }
        if ($affiliateLink !== '') {
            $lines[] = "- Affiliate signup: {$affiliateLink}";
        }
        $lines[] = '';

        $lines[] = '## How the AI Assistant Should Respond';
        $lines[] = "- Answer attendee questions about {$productName} using the facts above.";
        $lines[] = '- Be helpful, concise, and conversational — like a knowledgeable webinar host.';
        $lines[] = '- When attendees ask about pricing, buying, signing up, or getting access, direct them to the CTA button or sales page.';
        $lines[] = '- Do not invent features, prices, guarantees, or launch dates not mentioned above.';
        $lines[] = '- If you do not know something specific, suggest they check the sales page or ask the host.';

        return implode("\n", $this->sanitizeUtf8Lines($lines));
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function sanitizeUtf8Lines(array $lines): array
    {
        return array_map(fn (string $line): string => $this->sanitizeUtf8($line), $lines);
    }

    public function sanitizeUtf8(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = iconv('UTF-8', 'UTF-8//IGNORE', $text) ?: $text;
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * Strip HTML and collapse whitespace from scraped JV page text.
     */
    public function cleanJvExcerpt(string $raw, int $maxChars = 3500): ?string
    {
        $raw = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/iu', ' ', $raw) ?? $raw;
        $raw = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/iu', ' ', $raw) ?? $raw;
        $raw = preg_replace('/<noscript\b[^>]*>[\s\S]*?<\/noscript>/iu', ' ', $raw) ?? $raw;
        $raw = preg_replace('/<!--[\s\S]*?-->/u', ' ', $raw) ?? $raw;

        $text = strip_tags($raw);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        if (! $this->isUsableExcerpt($text)) {
            return null;
        }

        $text = $this->sanitizeUtf8($text);

        if (strlen($text) <= $maxChars) {
            return $text;
        }

        $truncated = rtrim(substr($text, 0, $maxChars), '.!? ').'…';

        return $this->isUsableExcerpt($truncated) ? $truncated : null;
    }

    public function isUsableExcerpt(string $text): bool
    {
        if (strlen($text) < 180) {
            return false;
        }

        $letterCount = preg_match_all('/[a-zA-Z]/', $text) ?: 0;
        if ($letterCount / max(1, strlen($text)) < 0.55) {
            return false;
        }

        $wordCount = str_word_count($text);
        if ($wordCount < 40) {
            return false;
        }

        // Reject CSS/JS-heavy scrapes
        if (preg_match('/@(media|font-face|keyframes)|fbq\(|dataLayer|select2-container/i', $text)) {
            return false;
        }

        return true;
    }
}
