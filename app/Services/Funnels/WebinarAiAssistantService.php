<?php

namespace App\Services\Funnels;

use App\Models\Funnel;
use App\Models\FunnelAiSource;
use App\Models\FunnelAiSourceChunk;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebinarAiAssistantService
{
    /**
     * @param  array<int, string>  $history
     * @return array{reply: ?string, reason: string}
     */
    public function generateReply(Funnel $funnel, string $message, array $history = []): array
    {
        $settings = $funnel->settings;
        if (! $settings || ! $settings->webinar_ai_enabled || ! $settings->webinar_ai_auto_reply_enabled) {
            return ['reply' => null, 'reason' => 'assistant_disabled'];
        }

        $sources = FunnelAiSource::query()
            ->where('funnel_id', $funnel->id)
            ->where('status', 'ready')
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get();

        if ($sources->isEmpty()) {
            return ['reply' => null, 'reason' => 'no_sources'];
        }

        $queryVector = $this->embeddingService->embed($message);
        if (! is_array($queryVector) || $queryVector === []) {
            return ['reply' => null, 'reason' => 'embedding_failed'];
        }

        $chunks = FunnelAiSourceChunk::query()
            ->where('funnel_id', $funnel->id)
            ->whereIn('funnel_ai_source_id', $sources->pluck('id')->all())
            ->whereNotNull('embedding')
            ->limit(400)
            ->get(['id', 'content', 'embedding']);

        if ($chunks->isEmpty()) {
            return ['reply' => null, 'reason' => 'no_indexed_chunks'];
        }

        $scored = $chunks->map(function (FunnelAiSourceChunk $chunk) use ($queryVector): array {
            $embedding = is_array($chunk->embedding) ? $chunk->embedding : [];
            return [
                'content' => (string) $chunk->content,
                'score' => $this->embeddingService->cosineSimilarity($queryVector, $embedding),
            ];
        })->sortByDesc('score')->take(8)->values();

        $knowledgeBlocks = $scored
            ->filter(fn (array $item): bool => (float) $item['score'] > 0.12)
            ->map(fn (array $item): string => $item['content'])
            ->values()
            ->all();

        if (empty($knowledgeBlocks)) {
            return ['reply' => null, 'reason' => 'empty_knowledge'];
        }

        $assistantName = trim((string) $settings->webinar_ai_assistant_name) !== ''
            ? trim((string) $settings->webinar_ai_assistant_name)
            : 'Webinar Assistant';

        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            return $this->keywordFallback($assistantName, $message, $knowledgeBlocks);
        }

        $context = implode("\n\n---\n\n", array_slice($knowledgeBlocks, 0, 10));
        $recent = implode("\n", array_slice($history, -6));

        try {
            $response = Http::withToken($apiKey)
                ->timeout(35)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.45,
                    'max_tokens' => 260,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "You are {$assistantName}, a webinar room assistant. Reply briefly, clearly, and only using provided knowledge/context. If the answer is not supported by context, respond with EXACTLY: NO_ANSWER",
                        ],
                        [
                            'role' => 'user',
                            'content' => "Knowledge:\n{$context}\n\nRecent chat:\n{$recent}\n\nAttendee question:\n{$message}",
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Webinar AI completion failed', ['status' => $response->status()]);
                return ['reply' => null, 'reason' => 'openai_failed'];
            }

            $payload = $response->json();
            $reply = trim((string) ($payload['choices'][0]['message']['content'] ?? ''));

            if ($reply === '' || Str::upper($reply) === 'NO_ANSWER') {
                return ['reply' => null, 'reason' => 'no_answer'];
            }

            return [
                'reply' => Str::limit($reply, 900, ''),
                'reason' => 'ok',
            ];
        } catch (\Throwable $e) {
            Log::warning('Webinar AI exception', ['error' => $e->getMessage()]);
            return ['reply' => null, 'reason' => 'openai_exception'];
        }
    }

    /**
     * @param  array<int, string>  $knowledgeBlocks
     * @return array{reply: ?string, reason: string}
     */
    private function keywordFallback(string $assistantName, string $message, array $knowledgeBlocks): array
    {
        $needleWords = array_filter(
            preg_split('/\W+/', Str::lower($message)) ?: [],
            fn (string $word): bool => Str::length($word) >= 4
        );

        if (empty($needleWords)) {
            return ['reply' => null, 'reason' => 'no_answer'];
        }

        foreach ($knowledgeBlocks as $chunk) {
            $lower = Str::lower($chunk);
            foreach ($needleWords as $word) {
                if (str_contains($lower, $word)) {
                    return [
                        'reply' => "{$assistantName}: ".Str::limit($chunk, 500, '...'),
                        'reason' => 'ok',
                    ];
                }
            }
        }

        return ['reply' => null, 'reason' => 'no_answer'];
    }

    public function __construct(
        private readonly WebinarAiEmbeddingService $embeddingService,
    ) {}
}

