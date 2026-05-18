<?php

namespace App\Services\TrafficAi;

use App\Models\FunnelSetting;
use App\Models\Mention;
use App\Support\TrafficAiLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class TrafficReplyGenerator
{
    /**
     * @return array{text: string, source: 'openai'|'fallback', warning: string|null}
     */
    public function generateWithMeta(Mention $mention, FunnelSetting $settings, string $affiliateUrl, string $platformKey): array
    {
        $maxChars = (int) (config('traffic_ai.openai.max_reply_chars')[$platformKey] ?? config('traffic_ai.openai.max_reply_chars.default'));
        $ownerContext = trim((string) $settings->traffic_ai_extra_context);
        $apiKey = config('services.openai.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            TrafficAiLogger::warning('reply generation used fallback — OPENAI_API_KEY missing', [
                'mention_id' => $mention->id,
            ]);

            return [
                'text' => $this->buildFallbackReply($mention, $affiliateUrl, $ownerContext, $maxChars, $platformKey),
                'source' => 'fallback',
                'warning' => 'OpenAI is not configured. Add OPENAI_API_KEY to your .env for smarter replies.',
            ];
        }

        $model = (string) config('services.openai.model', 'gpt-4o-mini');

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('traffic_ai.openai.timeout', 45))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.72,
                    'max_tokens' => $this->maxTokensForPlatform($platformKey),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt($platformKey, $maxChars, $ownerContext),
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->userPrompt($mention, $affiliateUrl, $ownerContext, $platformKey),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                TrafficAiLogger::warning('reply generation used fallback — OpenAI HTTP error', [
                    'mention_id' => $mention->id,
                    'status' => $response->status(),
                ]);

                return [
                    'text' => $this->buildFallbackReply($mention, $affiliateUrl, $ownerContext, $maxChars, $platformKey),
                    'source' => 'fallback',
                    'warning' => 'OpenAI request failed. Check OPENAI_API_KEY and billing, then try again.',
                ];
            }

            $parsed = json_decode((string) $response->body(), true, 512, JSON_THROW_ON_ERROR);
            $text = trim((string) ($parsed['choices'][0]['message']['content'] ?? ''));

            if ($text === '' || $this->looksLikeLowQualityReply($text)) {
                TrafficAiLogger::warning('reply generation used fallback — empty or low-quality OpenAI output', [
                    'mention_id' => $mention->id,
                ]);

                return [
                    'text' => $this->buildFallbackReply($mention, $affiliateUrl, $ownerContext, $maxChars, $platformKey),
                    'source' => 'fallback',
                    'warning' => 'OpenAI returned a weak reply. Using a template based on your More context instead.',
                ];
            }

            if (! str_contains($text, $affiliateUrl)) {
                $text = rtrim($text, " \n").' '.$affiliateUrl;
            }

            return [
                'text' => Str::limit($text, $maxChars, ''),
                'source' => 'openai',
                'warning' => null,
            ];
        } catch (\Throwable $e) {
            TrafficAiLogger::warning('reply generation used fallback — exception', [
                'mention_id' => $mention->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'text' => $this->buildFallbackReply($mention, $affiliateUrl, $ownerContext, $maxChars, $platformKey),
                'source' => 'fallback',
                'warning' => 'Could not reach OpenAI. Using a template based on your More context.',
            ];
        }
    }

    public function generate(Mention $mention, FunnelSetting $settings, string $affiliateUrl, string $platformKey): ?string
    {
        $result = $this->generateWithMeta($mention, $settings, $affiliateUrl, $platformKey);

        return $result['text'] !== '' ? $result['text'] : null;
    }

    private function systemPrompt(string $platformKey, int $maxChars, string $ownerContext): string
    {
        $platformLabel = match ($platformKey) {
            'twitter' => 'X (Twitter)',
            'reddit' => 'Reddit',
            'youtube' => 'YouTube',
            default => $platformKey,
        };

        $lengthHint = match ($platformKey) {
            'twitter' => 'Keep it very short (1–2 tight sentences).',
            'reddit' => 'You may use 2–4 sentences if helpful.',
            default => 'Be concise but substantive (2–3 sentences).',
        };

        $contextBlock = $ownerContext !== ''
            ? "OWNER CONTEXT (mandatory — this is what you are selling; lead with the strongest hook here, especially contests, commission %, or deadlines):\n{$ownerContext}"
            : 'OWNER CONTEXT: none provided — infer a helpful angle from the post and still drive clicks to the link.';

        return <<<PROMPT
You write public replies on {$platformLabel} that promote the owner's offer and get clicks on their link.

{$contextBlock}

Goals:
- Sell / invite — not casual chit-chat. Every reply should give a reason to click the promotion link.
- When owner context mentions a contest or prize: open with that contest in the first sentence.
- When owner context mentions commission or affiliate program: make that prominent early.
- Briefly acknowledge what the original post is about (product/topic), then connect it to the offer.
- Include the promotion URL exactly once, woven in naturally (plain URL is fine).

Hard rules:
- Never start with "Saw your post about" or paste @handles, dates, or usernames from the post.
- Never use filler like "this helped me wrap my head around it".
- Stay at or under {$maxChars} characters. {$lengthHint}
- Sound human and specific, not generic marketing boilerplate.
PROMPT;
    }

    private function userPrompt(Mention $mention, string $affiliateUrl, string $ownerContext, string $platformKey): string
    {
        return json_encode([
            'promotion_link' => $affiliateUrl,
            'owner_context' => $ownerContext !== '' ? $ownerContext : null,
            'platform' => $platformKey,
            'post_topic_summary' => $this->mentionTopicSummary($mention),
            'post_full_text' => Str::limit($this->mentionBody($mention), 3500, ''),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function buildFallbackReply(
        Mention $mention,
        string $affiliateUrl,
        string $ownerContext,
        int $maxChars,
        string $platformKey,
    ): string {
        $topic = $this->mentionTopicSummary($mention);
        $link = $affiliateUrl;

        if ($ownerContext !== '') {
            $hook = $this->contextHook($ownerContext);

            if ($this->contextMentionsContest($ownerContext)) {
                $base = "{$hook} Saw your {$topic} update — affiliates can jump in here: {$link}";
            } else {
                $base = "{$hook} Your {$topic} post is a great fit for this — details: {$link}";
            }
        } else {
            $base = "Your {$topic} post looks solid — if you're open to partnering on this niche, worth a look: {$link}";
        }

        if ($platformKey === 'twitter') {
            $base = Str::limit($base, min($maxChars, 260), '');
        }

        return Str::limit($base, $maxChars, '');
    }

    private function contextHook(string $ownerContext): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($ownerContext)) ?? trim($ownerContext);

        if (preg_match('/(\$[\d,]+(?:\s*contest|\s*prize)?|contest[^.!?]{0,80})/i', $normalized, $match)) {
            $snippet = trim($match[1]);
            $rest = trim(Str::after($normalized, $snippet));

            return "Heads up — {$snippet}".($rest !== '' ? '. '.Str::limit($rest, 100, '…') : '.');
        }

        if (preg_match('/(\d+\s*%[^.!?]{0,40})/i', $normalized, $match)) {
            return Str::limit(trim($match[1]).'. '.Str::after($normalized, $match[1]), 160, '…');
        }

        return Str::limit($normalized, 160, '…');
    }

    private function contextMentionsContest(string $ownerContext): bool
    {
        return (bool) preg_match('/\bcontest\b|\bprize\b|\bgiveaway\b/i', $ownerContext);
    }

    private function mentionBody(Mention $mention): string
    {
        $body = trim((string) ($mention->content ?? ''));
        if ($body !== '') {
            return preg_replace('/\s+/', ' ', $body) ?? $body;
        }

        return preg_replace('/\s+/', ' ', trim((string) ($mention->title ?? ''))) ?? '';
    }

    private function mentionTopicSummary(Mention $mention): string
    {
        $body = $this->mentionBody($mention);

        if (preg_match('/Building\s+([^—\-–]+)/iu', $body, $match)) {
            return trim($match[1]);
        }

        if (preg_match('/^([^.|!?]{10,80})/u', $body, $match)) {
            return Str::limit(trim($match[1]), 80, '…');
        }

        return Str::limit($body, 80, '…') ?: 'this';
    }

    private function looksLikeLowQualityReply(string $text): bool
    {
        return str_contains(strtolower($text), 'saw your post about')
            || str_contains(strtolower($text), 'helped me wrap my head around it');
    }

    private function maxTokensForPlatform(string $platformKey): int
    {
        return match ($platformKey) {
            'twitter' => 120,
            'reddit' => 500,
            default => 350,
        };
    }
}
