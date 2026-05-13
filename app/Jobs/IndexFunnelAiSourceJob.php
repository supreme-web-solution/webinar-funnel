<?php

namespace App\Jobs;

use App\Models\FunnelAiSource;
use App\Models\FunnelAiSourceChunk;
use App\Services\Funnels\WebinarAiEmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexFunnelAiSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        public int $sourceId,
    ) {
        $this->onQueue('webinar-ai');
    }

    public function handle(WebinarAiEmbeddingService $embeddings): void
    {
        $source = FunnelAiSource::query()->find($this->sourceId);
        if (! $source) {
            return;
        }

        $source->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        $chunks = $source->chunks(700);
        if ($chunks === []) {
            $source->update([
                'status' => 'failed',
                'error_message' => 'No readable content found for indexing.',
                'chunk_count' => 0,
                'processed_at' => now(),
            ]);
            return;
        }

        FunnelAiSourceChunk::query()
            ->where('funnel_ai_source_id', $source->id)
            ->delete();

        $totalChunks = 0;
        $embeddedChunks = 0;
        foreach ($chunks as $idx => $chunkText) {
            $vector = $embeddings->embed($chunkText);
            FunnelAiSourceChunk::query()->create([
                'funnel_id' => $source->funnel_id,
                'funnel_ai_source_id' => $source->id,
                'chunk_index' => $idx + 1,
                'content' => $chunkText,
                'embedding' => $vector,
                'embedding_dimensions' => is_array($vector) ? count($vector) : 0,
            ]);
            $totalChunks++;
            if (is_array($vector) && $vector !== []) {
                $embeddedChunks++;
            }
        }

        if ($embeddedChunks === 0) {
            $source->update([
                'status' => 'failed',
                'chunk_count' => 0,
                'processed_at' => now(),
                'error_message' => 'Embedding generation failed for all chunks. Check OpenAI credentials/quota.',
            ]);
            return;
        }

        $source->update([
            'status' => 'ready',
            'chunk_count' => $embeddedChunks,
            'processed_at' => now(),
            'error_message' => $embeddedChunks < $totalChunks
                ? "Only {$embeddedChunks}/{$totalChunks} chunks were embedded. Some content may be skipped."
                : null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $source = FunnelAiSource::query()->find($this->sourceId);
        if ($source) {
            $source->update([
                'status' => 'failed',
                'error_message' => 'Indexing failed: '.$e->getMessage(),
                'processed_at' => now(),
            ]);
        }

        Log::warning('IndexFunnelAiSourceJob failed', [
            'source_id' => $this->sourceId,
            'error' => $e->getMessage(),
        ]);
    }
}

