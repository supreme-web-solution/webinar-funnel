<?php

namespace App\Http\Controllers;

use App\Jobs\IndexFunnelAiSourceJob;
use App\Models\Funnel;
use App\Models\FunnelAiSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FunnelAiSourceController extends Controller
{
    public function index(Request $request, Funnel $funnel): JsonResponse
    {
        $this->authorizeFunnel($funnel);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(20, (int) $request->query('per_page', 8)));

        $result = FunnelAiSource::query()
            ->where('funnel_id', $funnel->id)
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $result->getCollection()->map(fn (FunnelAiSource $source): array => $this->toSourcePayload($source))->values(),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function storeUrl(Request $request, Funnel $funnel): RedirectResponse
    {
        $this->authorizeFunnel($funnel);
        $this->ensureSourceLimit($funnel);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $content = $this->fetchUrlContent($validated['url']);
        $source = FunnelAiSource::query()->create([
            'funnel_id' => $funnel->id,
            'type' => FunnelAiSource::TYPE_URL,
            'title' => $validated['title'] ?: parse_url($validated['url'], PHP_URL_HOST),
            'source_url' => $validated['url'],
            'status' => $content === null ? 'failed' : 'queued',
            'error_message' => $content === null ? 'Unable to scrape URL content' : null,
            'content' => $content,
            'processed_at' => $content === null ? now() : null,
            'chunk_count' => 0,
        ]);

        if ($content !== null) {
            IndexFunnelAiSourceJob::dispatch((int) $source->id);
        }

        return back()->with('success', $source->status === 'queued'
            ? 'Website source queued for ingestion.'
            : 'Website source saved but scraping failed.');
    }

    public function storeTranscript(Request $request, Funnel $funnel): RedirectResponse
    {
        $this->authorizeFunnel($funnel);
        $this->ensureSourceLimit($funnel);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'transcript' => ['required', 'string', 'max:200000'],
        ]);

        $content = trim($validated['transcript']);
        $source = FunnelAiSource::query()->create([
            'funnel_id' => $funnel->id,
            'type' => FunnelAiSource::TYPE_TEXT,
            'title' => $validated['title'] ?: 'Pasted transcript',
            'source_url' => null,
            'status' => 'queued',
            'error_message' => null,
            'content' => $content,
            'processed_at' => null,
            'chunk_count' => 0,
        ]);

        IndexFunnelAiSourceJob::dispatch((int) $source->id);

        return back()->with('success', $source->id ? 'Transcript source queued for ingestion.' : 'Transcript source saved.');
    }

    public function storeFile(Request $request, Funnel $funnel): RedirectResponse
    {
        $this->authorizeFunnel($funnel);
        $this->ensureSourceLimit($funnel);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,txt,md,csv,xlsx,xls,docx'],
        ]);

        $file = $validated['file'];
        $path = $file->store("funnel-ai/{$funnel->id}", ['disk' => 'local']);
        $raw = Storage::disk('local')->get($path);
        $content = trim((string) $raw);

        $source = FunnelAiSource::query()->create([
            'funnel_id' => $funnel->id,
            'type' => FunnelAiSource::TYPE_FILE,
            'title' => $validated['title'] ?: $file->getClientOriginalName(),
            'source_url' => null,
            'status' => $content === '' ? 'failed' : 'queued',
            'error_message' => $content === '' ? 'Could not parse file content' : null,
            'content' => $content === '' ? null : $content,
            'processed_at' => $content === '' ? now() : null,
            'chunk_count' => 0,
        ]);

        if ($content !== '') {
            IndexFunnelAiSourceJob::dispatch((int) $source->id);
        }

        return back()->with('success', $source->status === 'queued'
            ? 'File source queued for ingestion.'
            : 'File uploaded but parsing failed.');
    }

    public function chunks(Request $request, Funnel $funnel, FunnelAiSource $source): JsonResponse
    {
        $this->authorizeFunnel($funnel);
        abort_unless((int) $source->funnel_id === (int) $funnel->id, 404);

        $chunks = $source->chunks();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 12)));
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($chunks, $offset, $perPage);

        return response()->json([
            'data' => array_values(array_map(
                fn (string $chunk, int $idx): array => [
                    'id' => "{$source->id}-".($offset + $idx),
                    'chunk_index' => $offset + $idx + 1,
                    'content' => $chunk,
                ],
                $slice,
                array_keys($slice)
            )),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil(max(1, count($chunks)) / $perPage)),
                'per_page' => $perPage,
                'total' => count($chunks),
            ],
        ]);
    }

    public function destroy(Funnel $funnel, FunnelAiSource $source): JsonResponse
    {
        $this->authorizeFunnel($funnel);
        abort_unless((int) $source->funnel_id === (int) $funnel->id, 404);

        $source->delete();
        \App\Models\FunnelAiSourceChunk::query()
            ->where('funnel_ai_source_id', $source->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function bulkDelete(Request $request, Funnel $funnel): JsonResponse
    {
        $this->authorizeFunnel($funnel);
        $validated = $request->validate([
            'source_ids' => ['required', 'array', 'min:1'],
            'source_ids.*' => ['integer'],
        ]);

        $deleted = FunnelAiSource::query()
            ->where('funnel_id', $funnel->id)
            ->whereIn('id', $validated['source_ids'])
            ->delete();
        \App\Models\FunnelAiSourceChunk::query()
            ->where('funnel_id', $funnel->id)
            ->whereIn('funnel_ai_source_id', $validated['source_ids'])
            ->delete();

        return response()->json(['deleted' => (int) $deleted]);
    }

    private function authorizeFunnel(Funnel $funnel): void
    {
        $authId = (int) (auth()->id() ?? 0);
        abort_unless($authId > 0 && $authId === (int) $funnel->user_id, 403);
    }

    private function ensureSourceLimit(Funnel $funnel): void
    {
        $count = FunnelAiSource::query()->where('funnel_id', $funnel->id)->count();
        if ($count >= 3) {
            throw ValidationException::withMessages([
                'source_limit' => 'Maximum of 3 AI sources allowed for this funnel.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toSourcePayload(FunnelAiSource $source): array
    {
        return [
            'id' => $source->id,
            'type' => $source->type,
            'title' => $source->title,
            'source_url' => $source->source_url,
            'status' => $source->status,
            'error_message' => $source->error_message,
            'processed_at' => $source->processed_at,
            'chunk_count' => (int) $source->chunk_count,
            'chunks_url' => route('funnels.ai.sources.chunks', [$source->funnel_id, $source->id]),
            'delete_url' => route('funnels.ai.sources.delete', [$source->funnel_id, $source->id]),
        ];
    }

    private function fetchUrlContent(string $url): ?string
    {
        $apiKey = trim((string) config('services.scrapingbee.api_key', ''));
        if ($apiKey === '') {
            return null;
        }

        try {
            $response = Http::timeout(45)->get('https://app.scrapingbee.com/api/v1/', [
                'api_key' => $apiKey,
                'url' => $url,
                'render_js' => 'true',
                'block_resources' => 'false',
                'wait_browser' => 'networkidle2',
            ]);

            if (! $response->successful()) {
                return null;
            }
            $html = (string) $response->body();
            $clean = trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));
            return Str::limit($clean, 100000, '');
        } catch (\Throwable) {
            return null;
        }
    }
}

