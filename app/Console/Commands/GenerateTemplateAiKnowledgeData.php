<?php

namespace App\Console\Commands;

use App\Services\Funnels\TemplateAiKnowledgeFormatter;
use Database\Seeders\TemplateSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;

class GenerateTemplateAiKnowledgeData extends Command
{
    protected $signature = 'templates:generate-ai-knowledge
                            {--batch=5 : Number of JV pages to fetch per run (0 = skip fetching)}
                            {--start=1 : First template sort_order to process}
                            {--end=51 : Last template sort_order to process}
                            {--force-fetch : Re-fetch JV pages even if excerpt already exists}';

    protected $description = 'Generate database/data/template_ai_knowledge.php from template offer data and JV pages';

    public function handle(TemplateAiKnowledgeFormatter $formatter): int
    {
        $offers = $this->loadOfferData();
        if (count($offers) !== 51) {
            $this->error('Expected 51 offers, got '.count($offers));

            return self::FAILURE;
        }

        $path = database_path('data/template_ai_knowledge.php');
        /** @var array<int, array{title: string, content: string, jv_page: string|null, jv_excerpt: string|null}> $existing */
        $existing = file_exists($path) ? require $path : [];

        /** @var array<int, string> $manualExcerpts */
        $manualExcerpts = file_exists(database_path('data/manual_jv_excerpts.php'))
            ? require database_path('data/manual_jv_excerpts.php')
            : [];

        $start = max(1, (int) $this->option('start'));
        $end = min(51, (int) $this->option('end'));
        $batch = max(0, (int) $this->option('batch'));
        $forceFetch = (bool) $this->option('force-fetch');
        $fetched = 0;

        for ($i = $start; $i <= $end; $i++) {
            $offer = $offers[$i - 1];
            $jvPage = trim((string) ($offer['jv_page'] ?? ''));
            $productName = (string) preg_replace('/\s+Offer$/i', '', (string) ($offer['name'] ?? "Template {$i}"));
            $jvExcerpt = $existing[$i]['jv_excerpt'] ?? null;

            if (isset($manualExcerpts[$i]) && trim($manualExcerpts[$i]) !== '') {
                $jvExcerpt = trim($manualExcerpts[$i]);
                $this->line("Using manual JV excerpt for #{$i} {$productName}.");
            }

            $shouldFetch = $jvPage !== ''
                && $batch > 0
                && $fetched < $batch
                && ! isset($manualExcerpts[$i])
                && ($forceFetch || empty($jvExcerpt));

            if ($shouldFetch) {
                $this->line("Fetching JV page for #{$i} {$productName}…");
                $jvExcerpt = $this->fetchJvExcerpt($jvPage, $formatter);
                $fetched++;
            } elseif (! $forceFetch && ! empty($jvExcerpt) && ! $formatter->isUsableExcerpt((string) $jvExcerpt)) {
                $jvExcerpt = null;
            }

            $existing[$i] = [
                'title' => "{$productName} Product Knowledge",
                'content' => $formatter->sanitizeUtf8($formatter->format($offer, $jvExcerpt)),
                'jv_page' => $jvPage !== '' ? $jvPage : null,
                'jv_excerpt' => $jvExcerpt !== null ? $formatter->sanitizeUtf8($jvExcerpt) : null,
            ];

            $this->info("Prepared knowledge for #{$i}: {$productName}");
        }

        ksort($existing);
        $this->writeDataFile($path, $existing);

        $this->newLine();
        $this->info("Wrote {$path}");
        if ($fetched > 0) {
            $this->comment("Fetched {$fetched} JV page(s) this run.");
        }
        if ($end < 51 || $fetched >= $batch && $batch > 0 && $end < 51) {
            $next = $end + 1;
            $this->comment("Run again with --start={$next} to continue JV fetching in batches.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadOfferData(): array
    {
        $seeder = new TemplateSeeder;
        $method = new ReflectionMethod(TemplateSeeder::class, 'offerData');
        $method->setAccessible(true);

        return $method->invoke($seeder);
    }

    private function fetchJvExcerpt(string $url, TemplateAiKnowledgeFormatter $formatter): ?string
    {
        try {
            $response = Http::timeout(25)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; WebinarForge/1.0)'])
                ->get($url);

            if (! $response->successful()) {
                $this->warn("  HTTP {$response->status()} for {$url}");

                return null;
            }

            return $formatter->cleanJvExcerpt($response->body());
        } catch (\Throwable $e) {
            $this->warn("  Failed to fetch {$url}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * @param  array<int, array{title: string, content: string, jv_page: string|null, jv_excerpt: string|null}>  $entries
     */
    private function writeDataFile(string $path, array $entries): void
    {
        $export = var_export($entries, true);
        $content = <<<PHP
<?php

/**
 * Pre-built AI knowledge base content for each DFY webinar template (sort_order 1–51).
 * Generated by: php artisan templates:generate-ai-knowledge
 *
 * @var array<int, array{title: string, content: string, jv_page: string|null, jv_excerpt: string|null}>
 */
return {$export};

PHP;

        file_put_contents($path, $content);
    }
}
