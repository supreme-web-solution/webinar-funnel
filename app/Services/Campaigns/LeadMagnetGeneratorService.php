<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignPage;
use App\Services\Ai\OpenRouterService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LeadMagnetGeneratorService
{
    private const TARGET_PAGES = 10;

    private const CHUNK_SIZE = 2;

    private const MIN_WORDS_PER_PAGE = 550;

    public function __construct(
        protected OpenRouterService $openRouter,
        protected CampaignKnowledgeBuilderService $knowledge,
        protected TrackedLinkService $trackedLinks,
        protected CampaignGenerationProgressService $progress,
        protected LeadMagnetPdfService $pdf,
    ) {}

    /**
     * @return array{ok: bool, suggestions: array<int, array<string, mixed>>, error: string|null}
     */
    public function suggest(Campaign $campaign): array
    {
        $ctx = $this->knowledge->contextForGeneration($campaign);
        $ideas = $ctx['pass2']['lead_magnet_ideas'] ?? [];

        if (is_array($ideas) && $ideas !== []) {
            $suggestions = array_values(array_map(
                fn ($row, $i) => $this->enrichLeadMagnetSuggestion(is_array($row) ? $row : [], $ctx, (int) $i),
                $ideas,
                array_keys($ideas),
            ));

            $this->upsertLeadMagnetPage($campaign, [
                'status' => 'suggestions',
                'suggestions' => $suggestions,
            ]);

            return ['ok' => true, 'suggestions' => $suggestions, 'error' => null];
        }

        $productName = (string) ($ctx['offer']['product_name'] ?? $ctx['pass1']['product_summary'] ?? 'the offer');

        $json = $this->openRouter->chatJsonWithFallback([
            [
                'role' => 'system',
                'content' => 'Return ONLY JSON {"suggestions":[{"id":"","title":"","format":"ebook|checklist|mini_course|quiz|template","description":"","why_it_converts":"","outline_bullets":[]}]} with 6 lead magnet ideas for THIS SPECIFIC OFFER ONLY ('.$productName.'). Each title must name the niche + deliverable (e.g. "10-Page Guide: [Topic] for [Audience]"). No generic affiliate/funnel titles unless the offer is about funnels. description = exactly what the user downloads. outline_bullets = 4–6 real chapter titles.',
            ],
            ['role' => 'user', 'content' => json_encode($ctx, JSON_PRETTY_PRINT)],
        ], 'lead_magnet_blueprint');

        $raw = $json['ok'] ? ($json['data']['suggestions'] ?? []) : [];
        if (! is_array($raw) || $raw === []) {
            return ['ok' => false, 'suggestions' => [], 'error' => 'Could not generate lead magnet suggestions. Build knowledge first.'];
        }

        $suggestions = array_values(array_map(
            fn ($row, $i) => $this->enrichLeadMagnetSuggestion(is_array($row) ? $row : [], $ctx, (int) $i),
            $raw,
            array_keys($raw),
        ));

        $this->upsertLeadMagnetPage($campaign, [
            'status' => 'suggestions',
            'suggestions' => $suggestions,
        ]);

        return ['ok' => true, 'suggestions' => $suggestions, 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    protected function enrichLeadMagnetSuggestion(array $row, array $ctx, int $index): array
    {
        $row = $this->anchorSuggestionToOffer($row, $ctx);

        $format = (string) ($row['format'] ?? 'ebook');
        $outline = is_array($row['outline_bullets'] ?? null) ? $row['outline_bullets'] : [];
        $outline = array_values(array_filter(array_map('strval', $outline)));
        $deliverable = $this->deliverableLabel($format);
        $description = trim((string) ($row['description'] ?? ''));
        $why = trim((string) ($row['why_it_converts'] ?? ''));
        $product = $this->resolveProductLabel($ctx);

        $previewParts = [$deliverable, "for {$product}"];
        if ($outline !== []) {
            $previewParts[] = 'Sections: '.implode(' · ', array_slice($outline, 0, 4));
        }

        return [
            'id' => (string) ($row['id'] ?? 'lm_'.($index + 1)),
            'title' => (string) ($row['title'] ?? 'Lead magnet'),
            'format' => $format,
            'format_label' => $this->formatLabel($format),
            'deliverable' => $deliverable,
            'description' => $description,
            'why_it_converts' => $why,
            'outline_bullets' => $outline,
            'preview' => implode(' — ', $previewParts),
            'offer_anchor' => (string) ($row['offer_anchor'] ?? "Pre-sell for: {$product}"),
            'from_knowledge' => (bool) ($row['from_knowledge'] ?? true),
        ];
    }

    /**
     * Rewrites generic pass2 ideas so each one names the actual offer being promoted.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    protected function anchorSuggestionToOffer(array $row, array $ctx): array
    {
        $product = $this->resolveProductLabel($ctx);
        $mechanism = trim((string) ($ctx['pass1']['unique_mechanism'] ?? ''));
        $title = trim((string) ($row['title'] ?? 'Lead magnet'));
        $description = trim((string) ($row['description'] ?? ''));
        $outline = is_array($row['outline_bullets'] ?? null) ? $row['outline_bullets'] : [];
        $format = (string) ($row['format'] ?? 'ebook');

        if (! $this->mentionsOffer($title.' '.$description, $ctx)) {
            $title = $this->buildAnchoredTitle($title, $format, $product);
            $description = $this->buildAnchoredDescription($description, $product, $mechanism);
            $outline = $this->anchorOutlineBullets($outline, $product, $mechanism);
        }

        return [
            ...$row,
            'title' => $title,
            'description' => $description,
            'outline_bullets' => $outline,
            'offer_anchor' => "Pre-sell for: {$product}",
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function resolveProductLabel(array $ctx): string
    {
        $name = trim((string) ($ctx['offer']['product_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $summary = trim((string) ($ctx['pass1']['product_summary'] ?? ''));
        if (preg_match('/^(.{5,72}?[.!]?)(?:\s|$)/', $summary, $m)) {
            return rtrim($m[1], '.!');
        }

        return 'this offer';
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function mentionsOffer(string $text, array $ctx): bool
    {
        $haystack = strtolower($text);
        $product = strtolower($this->resolveProductLabel($ctx));

        if ($product !== 'this offer' && str_contains($haystack, strtolower($product))) {
            return true;
        }

        foreach (preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/', ' ', $product)) as $word) {
            if (strlen($word) >= 5 && str_contains($haystack, $word)) {
                return true;
            }
        }

        $mechanism = strtolower((string) ($ctx['pass1']['unique_mechanism'] ?? ''));
        foreach (['paste', 'sales page url', '3 minute', 'three minute', 'commission machine', 'automated'] as $keyword) {
            if (str_contains($mechanism, $keyword) && str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function buildAnchoredTitle(string $genericTitle, string $format, string $product): string
    {
        $shortProduct = Str::limit($product, 48, '');
        $topic = preg_replace('/^(The|A|An)\s+/i', '', $genericTitle) ?? $genericTitle;

        return match ($format) {
            'checklist' => "{$shortProduct} Checklist: {$topic}",
            'quiz' => "Quiz: Is {$shortProduct} Right for Your Funnel?",
            'template' => "{$shortProduct} Templates — {$topic}",
            'mini_course' => "Mini-Course: {$topic} (with {$shortProduct})",
            default => "{$shortProduct}: {$topic}",
        };
    }

    protected function buildAnchoredDescription(string $description, string $product, string $mechanism): string
    {
        $parts = [];

        if (! str_contains(strtolower($description), strtolower($product))) {
            $parts[] = "Free download for people considering {$product}.";
        }

        $parts[] = $description !== '' ? $description : 'A practical resource tied to the offer you imported.';

        if ($mechanism !== '' && ! str_contains(strtolower($description), 'lead')) {
            $parts[] = 'Shows why '.$mechanism;
        }

        $parts[] = "Natural next step: get {$product}.";

        return trim(implode(' ', array_filter($parts)));
    }

    /**
     * @param  array<int, mixed>  $outline
     * @return array<int, string>
     */
    protected function anchorOutlineBullets(array $outline, string $product, string $mechanism): array
    {
        $outline = array_values(array_filter(array_map('strval', $outline)));

        $intro = "What {$product} does".($mechanism !== '' ? ' — '.Str::limit($mechanism, 70) : '');
        if ($outline === [] || ! str_contains(strtolower($outline[0]), strtolower(Str::limit($product, 20)))) {
            array_unshift($outline, $intro);
        }

        $outline[] = "Your next step: activate {$product}";

        return array_slice($outline, 0, 6);
    }

    protected function formatLabel(string $format): string
    {
        return match ($format) {
            'checklist' => 'Printable checklist',
            'mini_course' => 'Mini-course',
            'quiz' => 'Interactive quiz',
            'template' => 'Template pack',
            default => 'PDF guide (~10 pages)',
        };
    }

    protected function deliverableLabel(string $format): string
    {
        return match ($format) {
            'checklist' => '1–2 page printable checklist PDF',
            'mini_course' => '5–8 lesson mini-course (slide-style pages)',
            'quiz' => '10-question quiz + personalized results page',
            'template' => '3–5 fill-in templates (copy-paste ready)',
            default => '~10-page designed PDF guide',
        };
    }

    /**
     * Multi-pass pipeline: blueprint → chunked content (2 pages/call) → polish → optional enhance on re-run.
     *
     * @return array{ok: bool, content: array<string, mixed>|null, error: string|null}
     */
    public function generate(Campaign $campaign, string $selectedId, string $mode = 'auto'): array
    {
        @set_time_limit(600);

        $page = $campaign->pages()->where('page_type', 'lead_magnet')->first();
        $suggestions = $page?->content['suggestions'] ?? [];
        $selected = collect($suggestions)->firstWhere('id', $selectedId);

        if (! is_array($selected)) {
            return ['ok' => false, 'content' => null, 'error' => 'Selected lead magnet not found.'];
        }

        $existing = is_array($page?->content) ? $page->content : [];
        $sameSelection = ($existing['selected_id'] ?? null) === $selectedId;
        $hasContent = ($existing['status'] ?? '') === 'ready'
            || (! empty($existing['pages']) && ! empty($existing['download_url']));
        $isReady = $hasContent && $sameSelection;

        if ($mode === 'auto' && $isReady) {
            return ['ok' => true, 'content' => $existing, 'error' => null, 'skipped' => true];
        }

        if ($mode === 'auto' && ($existing['generated'] ?? false) === true && $sameSelection) {
            return ['ok' => true, 'content' => $existing, 'error' => null, 'skipped' => true];
        }

        $ctx = $this->knowledge->contextForGeneration($campaign);
        $timeout = $this->openRouter->leadMagnetTimeout();

        $affiliate = (string) ($campaign->affiliate_link ?? '#');
        $cloaked = $this->trackedLinks->createForCampaign($campaign, $affiliate, 'Lead magnet footer CTA');
        $affiliateUrl = $cloaked->publicUrl();

        $state = [
            'status' => $existing['status'] ?? 'generating',
            'selected_id' => $selectedId,
            'selected' => $selected,
            'generation_log' => $existing['generation_log'] ?? [],
            'blueprint' => $existing['blueprint'] ?? null,
            'design_system' => $existing['design_system'] ?? null,
            'pages' => $existing['pages'] ?? [],
            'polish_pass' => (int) ($existing['polish_pass'] ?? 0),
            'enhance_pass' => (int) ($existing['enhance_pass'] ?? 0),
        ];

        if ($mode === 'restart') {
            $state['blueprint'] = null;
            $state['design_system'] = null;
            $state['pages'] = [];
            $state['polish_pass'] = 0;
            $state['enhance_pass'] = 0;
            $state['generation_log'] = [];
            $state['status'] = 'generating';
        }

        $state['status'] = 'generating';
        $state['selected_id'] = $selectedId;
        $state['selected'] = $selected;
        $this->persistState($campaign, $state, $affiliateUrl, $cloaked->code);

        $this->progress->update($campaign, 'init', 'Selected: '.($selected['title'] ?? 'Lead magnet'), 8, [
            'detail' => 'Format: '.($selected['format'] ?? 'guide'),
            'selected_id' => $selectedId,
            'selected_title' => $selected['title'] ?? null,
        ]);

        $sameSelection = ($existing['selected_id'] ?? null) === $selectedId;
        $isReady = ($existing['status'] ?? '') === 'ready' && $sameSelection;
        $shouldEnhance = $mode === 'enhance';

        if ($shouldEnhance && ! empty($state['pages'])) {
            return $this->runEnhancePass($campaign, $state, $ctx, $affiliateUrl, $cloaked->code, $timeout);
        }

        if ($state['blueprint'] === null) {
            $this->progress->update($campaign, 'blueprint', 'Building 10-page PRD blueprint from knowledge…', 12, [
                'detail' => 'Extracting page titles, sections, design system',
            ]);

            $blueprintResult = $this->buildBlueprint($selected, $ctx, $timeout);
            if (! $blueprintResult['ok']) {
                return ['ok' => false, 'content' => null, 'error' => $blueprintResult['error'] ?? 'Blueprint failed.'];
            }

            $state['blueprint'] = $blueprintResult['blueprint'];
            $state['design_system'] = $blueprintResult['design_system'];
            $pageTitles = collect($state['blueprint']['pages'] ?? [])->pluck('title')->filter()->take(4)->values()->all();

            $this->progress->update($campaign, 'blueprint_done', 'Blueprint received from AI', 22, [
                'detail' => 'Title: '.($state['blueprint']['title'] ?? '').' · '.count($state['blueprint']['pages'] ?? []).' pages planned',
                'model' => $blueprintResult['model'] ?? null,
                'extracted' => ['page_titles' => $pageTitles],
                'pages_total' => count($state['blueprint']['pages'] ?? []),
            ]);
            $state['generation_log'][] = [
                'step' => 'blueprint',
                'model' => $blueprintResult['model'] ?? null,
                'at' => now()->toIso8601String(),
            ];
            $this->persistState($campaign, $state, $affiliateUrl, $cloaked->code);
        }

        $blueprintPages = $state['blueprint']['pages'] ?? [];
        $writtenPageNums = collect($state['pages'])->pluck('page')->map(fn ($n) => (int) $n)->all();
        $missing = collect($blueprintPages)
            ->filter(fn ($p) => ! in_array((int) ($p['page'] ?? 0), $writtenPageNums, true))
            ->values()
            ->all();

        foreach (array_chunk($missing, self::CHUNK_SIZE) as $chunk) {
            if ($chunk === []) {
                continue;
            }

            $pageNums = array_map(fn ($p) => (int) ($p['page'] ?? 0), $chunk);
            $done = count($state['pages']);
            $total = count($blueprintPages);
            $pct = 25 + (int) round(($done / max($total, 1)) * 45);

            $this->progress->update($campaign, 'content_chunk', 'Writing pages '.implode(', ', $pageNums).'…', $pct, [
                'detail' => 'Sending chunk to content model (550+ words/page, cards & checklists)',
                'pages_done' => $done,
                'pages_total' => $total,
            ]);

            $chunkResult = $this->writePageChunk(
                $state['blueprint'],
                $state['design_system'],
                $chunk,
                $ctx,
                $timeout
            );

            if (! $chunkResult['ok']) {
                $this->persistState($campaign, $state, $affiliateUrl, $cloaked->code);

                return [
                    'ok' => false,
                    'content' => $this->assembleContent($state, $affiliateUrl, $cloaked->code),
                    'error' => ($chunkResult['error'] ?? 'Content chunk failed.').' Saved progress — click Generate again to continue from page '.(count($state['pages']) + 1).'.',
                ];
            }

            foreach ($chunkResult['pages'] as $p) {
                $state['pages'][] = [
                    ...$p,
                    'footer_cta_url' => $affiliateUrl,
                ];
            }

            $state['generation_log'][] = [
                'step' => 'content_chunk',
                'pages' => array_column($chunkResult['pages'], 'page'),
                'model' => $chunkResult['model'] ?? null,
                'at' => now()->toIso8601String(),
            ];

            $state['pages'] = $this->sortPages($state['pages']);
            $this->persistState($campaign, $state, $affiliateUrl, $cloaked->code);

            $this->progress->update($campaign, 'content_chunk_done', 'AI response saved — pages '.implode(', ', $pageNums), $pct + 3, [
                'detail' => 'Model: '.($chunkResult['model'] ?? 'unknown'),
                'pages_done' => count($state['pages']),
                'pages_total' => $total,
                'model' => $chunkResult['model'] ?? null,
            ]);
        }

        if (count($state['pages']) < 3) {
            return ['ok' => false, 'content' => null, 'error' => 'Lead magnet content generation incomplete.'];
        }

        if ($state['polish_pass'] < 1) {
            $this->progress->update($campaign, 'polish', 'Polish pass 1 — applying design system & visual components…', 72);
            $polishResult = $this->polishAllPages($state, $ctx, $affiliateUrl, $timeout, expand: false);
            if ($polishResult['ok']) {
                $state['pages'] = $polishResult['pages'];
                $state['polish_pass'] = 1;
                $state['generation_log'][] = [
                    'step' => 'polish',
                    'model' => $polishResult['model'] ?? null,
                    'at' => now()->toIso8601String(),
                ];
                $this->persistState($campaign, $state, $affiliateUrl, $cloaked->code);
            }
        }

        if ($state['polish_pass'] < 2) {
            $this->progress->update($campaign, 'polish_expand', 'Polish pass 2 — expanding depth & removing generic fluff…', 85);
            $polishResult = $this->polishAllPages($state, $ctx, $affiliateUrl, $timeout, expand: true);
            if ($polishResult['ok']) {
                $state['pages'] = $polishResult['pages'];
                $state['polish_pass'] = 2;
                $state['generation_log'][] = [
                    'step' => 'polish_expand',
                    'model' => $polishResult['model'] ?? null,
                    'at' => now()->toIso8601String(),
                ];
                $this->persistState($campaign, $state, $affiliateUrl, $cloaked->code);
            }
        }

        $this->progress->update($campaign, 'finalize', 'Assembling printable HTML document…', 95);

        $state['status'] = 'ready';
        $content = $this->finalizeContent($campaign, $state, $affiliateUrl, $cloaked->code);

        return ['ok' => true, 'content' => $content, 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $selected
     * @param  array<string, mixed>  $ctx
     * @return array{ok: bool, blueprint?: array<string, mixed>, design_system?: array<string, mixed>, error?: string, model?: string}
     */
    protected function buildBlueprint(array $selected, array $ctx, int $timeout): array
    {
        $json = $this->openRouter->chatJsonWithFallback([
            [
                'role' => 'system',
                'content' => 'You are a lead magnet product designer. Create a detailed PRD-style blueprint for a premium downloadable resource (NOT generic fluff).

Return ONLY JSON:
{
  "title": "",
  "subtitle": "",
  "design_system": {
    "primary": "#hex",
    "secondary": "#hex",
    "accent": "#hex",
    "background": "#hex",
    "text": "#hex",
    "style_notes": "modern|professional|bold"
  },
  "pages": [
    {
      "page": 1,
      "title": "",
      "page_type": "cover|intro|chapter|checklist|worksheet|case_study|conclusion",
      "target_words": 600,
      "learning_objective": "",
      "sections": [{"heading":"","bullets":[],"must_include":""}],
      "visual_components": ["stat_cards","checklist","tip_callout","step_list","comparison_table","quote"],
      "offer_tie_in": "how this page connects to the main affiliate offer"
    }
  ]
}

Requirements:
- Exactly '.self::TARGET_PAGES.' pages
- Each page target_words >= '.self::MIN_WORDS_PER_PAGE.'
- Use specific pain points, hooks, and audience language from the knowledge dossier
- Page 1 = compelling cover/intro; middle pages = actionable depth; final page = strong bridge to main offer
- visual_components must vary across pages (not all the same)',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'selected_lead_magnet' => $selected,
                    'knowledge' => $ctx,
                ], JSON_PRETTY_PRINT),
            ],
        ], 'lead_magnet_blueprint', 'free_fallback', $timeout);

        if (! $json['ok'] || ! is_array($json['data'])) {
            return ['ok' => false, 'error' => $json['error'] ?? 'Blueprint generation failed.'];
        }

        $pages = $json['data']['pages'] ?? [];
        if (! is_array($pages) || count($pages) < 3) {
            return ['ok' => false, 'error' => 'Blueprint did not include enough pages.'];
        }

        return [
            'ok' => true,
            'blueprint' => [
                'title' => (string) ($json['data']['title'] ?? $selected['title']),
                'subtitle' => (string) ($json['data']['subtitle'] ?? ''),
                'pages' => array_values($pages),
            ],
            'design_system' => is_array($json['data']['design_system'] ?? null)
                ? $json['data']['design_system']
                : $this->defaultDesignSystem(),
            'model' => $json['model_used'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @param  array<string, mixed>|null  $designSystem
     * @param  array<int, array<string, mixed>>  $chunk
     * @param  array<string, mixed>  $ctx
     * @return array{ok: bool, pages?: array<int, array<string, mixed>>, error?: string, model?: string}
     */
    protected function writePageChunk(array $blueprint, ?array $designSystem, array $chunk, array $ctx, int $timeout): array
    {
        $result = $this->requestPageChunk($blueprint, $designSystem, $chunk, $ctx, $timeout);

        if ($result['ok']) {
            return $result;
        }

        if (count($chunk) <= 1) {
            return $result;
        }

        Log::warning('[LeadMagnet] chunk failed — retrying one page at a time', [
            'pages' => array_column($chunk, 'page'),
            'error' => $result['error'] ?? null,
        ]);

        $pages = [];
        $model = null;

        foreach ($chunk as $single) {
            $singleResult = $this->requestPageChunk($blueprint, $designSystem, [$single], $ctx, $timeout);
            if (! $singleResult['ok']) {
                return $singleResult;
            }

            foreach ($singleResult['pages'] as $p) {
                $pages[] = $p;
            }
            $model = $singleResult['model'] ?? $model;
        }

        return ['ok' => true, 'pages' => $pages, 'model' => $model];
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @param  array<string, mixed>|null  $designSystem
     * @param  array<int, array<string, mixed>>  $chunk
     * @param  array<string, mixed>  $ctx
     * @return array{ok: bool, pages?: array<int, array<string, mixed>>, error?: string, model?: string}
     */
    protected function requestPageChunk(array $blueprint, ?array $designSystem, array $chunk, array $ctx, int $timeout): array
    {
        $design = $designSystem ?? $this->defaultDesignSystem();
        $minWords = self::MIN_WORDS_PER_PAGE;
        $pageCount = count($chunk);
        $maxTokens = (int) config('services.openrouter.lead_magnet_max_tokens', 8192);

        $json = $this->openRouter->chatJsonWithFallback([
            [
                'role' => 'system',
                'content' => "You write premium lead magnet page HTML. Return ONLY valid JSON {\"pages\":[{\"page\":number,\"title\":\"\",\"body_html\":\"\",\"footer_cta_text\":\"\"}]}.

RULES:
- Write exactly {$pageCount} page(s) as requested
- Minimum {$minWords} words of substantive content PER page (count visible text, not HTML tags)
- Use these CSS classes: lm-intro, lm-card, lm-card-accent, lm-checklist, lm-tip, lm-warning, lm-steps, lm-stat-grid, lm-stat, lm-quote, lm-table, lm-exercise
- Each page MUST include: 1 intro paragraph, 2 structured sections with h3 headings, 1 lm-card, 1 lm-checklist OR lm-steps
- Write like a professional publisher — short paragraphs, concrete niche examples, numbered frameworks
- BANNED phrases: \"In today's world\", \"game-changer\", \"unlock your potential\", \"dive deep\", \"leverage\"
- Include specific examples, numbers, or mini case studies tied to the offer niche
- body_html must be a valid JSON string: escape every double quote inside HTML as \\\"
- body_html is an HTML fragment only (no html/head/body tags)
- footer_cta_text: short teaser linking reader toward the main offer
- Output raw JSON only — no markdown code fences",
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'lead_magnet_title' => $blueprint['title'] ?? '',
                    'lead_magnet_subtitle' => $blueprint['subtitle'] ?? '',
                    'design_system' => $design,
                    'pages_to_write' => $chunk,
                    'knowledge_dossier' => $ctx,
                ], JSON_PRETTY_PRINT),
            ],
        ], 'lead_magnet_content', 'lead_magnet_blueprint', $timeout, $maxTokens);

        if (! $json['ok'] || ! is_array($json['data']['pages'] ?? null)) {
            return ['ok' => false, 'error' => $json['error'] ?? 'Page chunk writing failed.'];
        }

        $pages = [];
        foreach ($json['data']['pages'] as $p) {
            $bodyHtml = (string) ($p['body_html'] ?? '');
            if ($bodyHtml === '' && isset($p['sections']) && is_array($p['sections'])) {
                $bodyHtml = $this->sectionsToHtml($p['sections']);
            }

            $pages[] = [
                'page' => (int) ($p['page'] ?? 0),
                'title' => (string) ($p['title'] ?? ''),
                'body_html' => $bodyHtml,
                'footer_cta_text' => (string) ($p['footer_cta_text'] ?? 'Discover the full solution →'),
            ];
        }

        if ($pages === []) {
            return ['ok' => false, 'error' => 'Page chunk returned no pages.'];
        }

        return ['ok' => true, 'pages' => $pages, 'model' => $json['model_used'] ?? null];
    }

    /**
     * Fallback when the model returns structured sections instead of body_html.
     *
     * @param  array<int, mixed>  $sections
     */
    protected function sectionsToHtml(array $sections): string
    {
        $html = '';
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $heading = (string) ($section['heading'] ?? $section['title'] ?? '');
            if ($heading !== '') {
                $html .= '<h3>'.e($heading).'</h3>';
            }
            if (isset($section['body'])) {
                $html .= '<p class="lm-intro">'.e((string) $section['body']).'</p>';
            }
            if (isset($section['bullets']) && is_array($section['bullets'])) {
                $html .= '<ul class="lm-checklist">';
                foreach ($section['bullets'] as $bullet) {
                    $html .= '<li>'.e((string) $bullet).'</li>';
                }
                $html .= '</ul>';
            }
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $ctx
     * @return array{ok: bool, pages?: array<int, array<string, mixed>>, model?: string}
     */
    protected function polishAllPages(array $state, array $ctx, string $affiliateUrl, int $timeout, bool $expand): array
    {
        $design = $state['design_system'] ?? $this->defaultDesignSystem();
        $pages = $state['pages'] ?? [];
        $polished = [];
        $maxTokens = (int) config('services.openrouter.lead_magnet_max_tokens', 8192);

        foreach (array_chunk($pages, 3) as $batch) {
            $instruction = $expand
                ? 'EXPAND and ENRICH each page: add deeper tactics, real examples from the dossier, another lm-card or lm-exercise, and ensure 700+ words. Fix any generic fluff.'
                : 'Apply polished visual design: ensure all required CSS classes are used, improve headings hierarchy, add stat cards or comparison tables where blueprint requested. Keep all factual content.';

            $json = $this->openRouter->chatJsonWithFallback([
                [
                    'role' => 'system',
                    'content' => "You are a lead magnet design editor. {$instruction}

Return ONLY JSON {\"pages\":[{\"page\":number,\"title\":\"\",\"body_html\":\"\",\"footer_cta_text\":\"\"}]}
Use CSS classes: lm-intro, lm-card, lm-card-accent, lm-checklist, lm-tip, lm-warning, lm-steps, lm-stat-grid, lm-stat, lm-quote, lm-table, lm-exercise",
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'design_system' => $design,
                        'blueprint_pages' => collect($state['blueprint']['pages'] ?? [])
                            ->whereIn('page', array_column($batch, 'page'))
                            ->values()
                            ->all(),
                        'draft_pages' => $batch,
                        'knowledge' => $ctx,
                    ], JSON_PRETTY_PRINT),
                ],
            ], 'lead_magnet_polish', 'lead_magnet_content', $timeout, $maxTokens);

            if ($json['ok'] && is_array($json['data']['pages'] ?? null)) {
                foreach ($json['data']['pages'] as $p) {
                    $polished[(int) $p['page']] = [
                        'page' => (int) ($p['page'] ?? 0),
                        'title' => (string) ($p['title'] ?? ''),
                        'body_html' => (string) ($p['body_html'] ?? ''),
                        'footer_cta_text' => (string) ($p['footer_cta_text'] ?? 'Discover the full solution →'),
                        'footer_cta_url' => $affiliateUrl,
                    ];
                }
            } else {
                Log::warning('[LeadMagnet] polish batch failed', ['error' => $json['error'] ?? null]);

                foreach ($batch as $p) {
                    $polished[(int) $p['page']] = [...$p, 'footer_cta_url' => $affiliateUrl];
                }
            }
        }

        ksort($polished);

        return ['ok' => true, 'pages' => array_values($polished), 'model' => $this->openRouter->modelFor('lead_magnet_polish')];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $ctx
     * @return array{ok: bool, content: array<string, mixed>|null, error: string|null}
     */
    protected function runEnhancePass(Campaign $campaign, array $state, array $ctx, string $affiliateUrl, string $cloakCode, int $timeout): array
    {
        $state['enhance_pass'] = ((int) ($state['enhance_pass'] ?? 0)) + 1;

        foreach (array_chunk($state['pages'] ?? [], 3) as $batch) {
            $json = $this->openRouter->chatJsonWithFallback([
                [
                    'role' => 'system',
                    'content' => 'You enhance an existing lead magnet for maximum perceived value. Return ONLY JSON {"pages":[{"page":number,"title":"","body_html":"","footer_cta_text":""}]}.

For EACH page in the batch:
- Double down on specificity (niche examples, step-by-step tactics, worksheets)
- Add at least 1 new visual component (lm-stat-grid, lm-table, lm-exercise)
- Minimum 750 words per page
- Remove generic marketing clichés
- Keep CSS classes: lm-card, lm-checklist, lm-tip, lm-steps, etc.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'enhance_pass_number' => $state['enhance_pass'],
                        'design_system' => $state['design_system'],
                        'blueprint_pages' => collect($state['blueprint']['pages'] ?? [])
                            ->whereIn('page', array_column($batch, 'page'))
                            ->values()
                            ->all(),
                        'current_pages' => $batch,
                        'knowledge' => $ctx,
                    ], JSON_PRETTY_PRINT),
                ],
            ], 'lead_magnet_polish', 'lead_magnet_content', $timeout, $maxTokens);

            if ($json['ok'] && is_array($json['data']['pages'] ?? null)) {
                foreach ($json['data']['pages'] as $p) {
                    $num = (int) ($p['page'] ?? 0);
                    $idx = collect($state['pages'])->search(fn ($x) => (int) ($x['page'] ?? 0) === $num);
                    if ($idx !== false) {
                        $state['pages'][$idx] = [
                            'page' => $num,
                            'title' => (string) ($p['title'] ?? $state['pages'][$idx]['title'] ?? ''),
                            'body_html' => (string) ($p['body_html'] ?? ''),
                            'footer_cta_text' => (string) ($p['footer_cta_text'] ?? $state['pages'][$idx]['footer_cta_text'] ?? ''),
                            'footer_cta_url' => $affiliateUrl,
                        ];
                    }
                }
            }
        }

        $state['generation_log'][] = [
            'step' => 'enhance_pass_'.$state['enhance_pass'],
            'model' => $json['model_used'] ?? null,
            'at' => now()->toIso8601String(),
        ];

        $state['status'] = 'ready';
        $content = $this->finalizeContent($campaign, $state, $affiliateUrl, $cloakCode);

        return ['ok' => true, 'content' => $content, 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function finalizeContent(Campaign $campaign, array $state, string $affiliateUrl, string $cloakCode): array
    {
        $title = (string) ($state['blueprint']['title'] ?? $state['selected']['title'] ?? 'Lead Magnet');
        $htmlDocument = $this->buildPrintableHtml(
            $title,
            (string) ($state['blueprint']['subtitle'] ?? ''),
            $state['pages'],
            $state['design_system'] ?? $this->defaultDesignSystem(),
            $affiliateUrl
        );

        $path = "campaigns/{$campaign->uuid}/lead-magnet.html";
        Storage::disk('public')->put($path, $htmlDocument);

        $pdfPath = "campaigns/{$campaign->uuid}/lead-magnet.pdf";
        try {
            $this->pdf->generateAndStore($htmlDocument, $pdfPath);
        } catch (\Throwable $e) {
            Log::warning('[LeadMagnet] PDF generation failed', ['error' => $e->getMessage()]);
            $pdfPath = null;
        }

        $content = [
            'status' => 'ready',
            'generated' => true,
            'selected_id' => $state['selected_id'],
            'selected' => $state['selected'],
            'title' => $title,
            'subtitle' => $state['blueprint']['subtitle'] ?? '',
            'blueprint' => $state['blueprint'],
            'design_system' => $state['design_system'],
            'pages' => $state['pages'],
            'page_count' => count($state['pages']),
            'polish_pass' => $state['polish_pass'],
            'enhance_pass' => $state['enhance_pass'],
            'generation_log' => $state['generation_log'],
            'download_path' => $path,
            'pdf_path' => $pdfPath,
            'download_url' => Storage::disk('public')->url($path),
            'affiliate_cloak_code' => $cloakCode,
        ];

        $this->upsertLeadMagnetPage($campaign, $content);
        $this->syncThankYouDownload($campaign, $title);

        return $content;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function persistState(Campaign $campaign, array $state, string $affiliateUrl, string $cloakCode): void
    {
        $content = $this->assembleContent($state, $affiliateUrl, $cloakCode);
        $this->upsertLeadMagnetPage($campaign, $content);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function assembleContent(array $state, string $affiliateUrl, string $cloakCode): array
    {
        return [
            'status' => $state['status'] ?? 'generating',
            'selected_id' => $state['selected_id'],
            'selected' => $state['selected'],
            'title' => $state['blueprint']['title'] ?? $state['selected']['title'] ?? 'Lead Magnet',
            'subtitle' => $state['blueprint']['subtitle'] ?? '',
            'blueprint' => $state['blueprint'],
            'design_system' => $state['design_system'],
            'pages' => $state['pages'],
            'page_count' => count($state['pages'] ?? []),
            'polish_pass' => $state['polish_pass'] ?? 0,
            'enhance_pass' => $state['enhance_pass'] ?? 0,
            'generation_log' => $state['generation_log'] ?? [],
            'affiliate_cloak_code' => $cloakCode,
            'download_url' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $design
     */
    protected function buildPrintableHtml(string $title, string $subtitle, array $pages, array $design, string $affiliateUrl): string
    {
        $primary = e((string) ($design['primary'] ?? '#4f46e5'));
        $secondary = e((string) ($design['secondary'] ?? '#0ea5e9'));
        $accent = e((string) ($design['accent'] ?? '#f59e0b'));
        $background = e((string) ($design['background'] ?? '#f8fafc'));
        $text = e((string) ($design['text'] ?? '#1e293b'));

        $css = $this->leadMagnetStyles($primary, $secondary, $accent, $background, $text);

        $cover = '<section class="lm-page lm-cover">'
            .'<div class="lm-cover-inner">'
            .'<p class="lm-cover-label">Free Guide</p>'
            .'<h1 class="lm-cover-title">'.e($title).'</h1>'
            .($subtitle !== '' ? '<p class="lm-cover-sub">'.e($subtitle).'</p>' : '')
            .'<p class="lm-cover-meta">'.count($pages).' pages · Actionable resource</p>'
            .'</div></section>';

        $body = $cover;
        foreach ($this->sortPages($pages) as $p) {
            $num = (int) ($p['page'] ?? 0);
            $body .= '<section class="lm-page">';
            $body .= '<div class="lm-page-header"><span class="lm-page-num">Page '.$num.'</span>';
            $body .= '<h2 class="lm-page-title">'.e($p['title'] ?? "Page {$num}").'</h2></div>';
            $body .= '<div class="lm-page-body">'.($p['body_html'] ?? '').'</div>';
            $body .= '<footer class="lm-footer">';
            $body .= '<p>'.e($p['footer_cta_text'] ?? 'Ready for the next step?').'</p>';
            $body .= '<a class="lm-footer-cta" href="'.e($affiliateUrl).'">'.e($p['footer_cta_text'] ?? 'Get the full solution').' →</a>';
            $body .= '</footer></section>';
        }

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.e($title).'</title><style>'.$css.'</style></head><body>'.$body.'</body></html>';
    }

    protected function leadMagnetStyles(string $primary, string $secondary, string $accent, string $background, string $text): string
    {
        return <<<CSS
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&display=swap');
:root { --primary: {$primary}; --secondary: {$secondary}; --accent: {$accent}; --bg: {$background}; --text: {$text}; --muted: #5c6b7a; --border: #e2e8f0; }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Inter', system-ui, sans-serif; color: var(--text); background: #eef1f5; line-height: 1.7; font-size: 15px; }
.lm-page { page-break-after: always; padding: 56px 64px; max-width: 720px; margin: 0 auto 24px; min-height: 90vh; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.lm-cover { display: flex; align-items: center; justify-content: center; text-align: center; background: linear-gradient(160deg, var(--primary) 0%, #2d4a6f 100%); color: #fff; }
.lm-cover-inner { max-width: 520px; }
.lm-cover-label { text-transform: uppercase; letter-spacing: 0.14em; font-size: 11px; font-weight: 600; opacity: 0.85; }
.lm-cover-title { font-family: 'Source Serif 4', Georgia, serif; font-size: 34px; font-weight: 700; margin: 20px 0 12px; line-height: 1.2; }
.lm-cover-sub { font-size: 17px; opacity: 0.92; margin-bottom: 28px; font-weight: 400; }
.lm-cover-meta { font-size: 13px; opacity: 0.75; }
.lm-page-header { border-bottom: 2px solid var(--primary); padding-bottom: 16px; margin-bottom: 32px; }
.lm-page-num { font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); font-weight: 600; }
.lm-page-title { font-family: 'Source Serif 4', Georgia, serif; font-size: 28px; font-weight: 700; margin: 8px 0 0; color: var(--primary); }
.lm-page-body { font-family: 'Source Serif 4', Georgia, serif; font-size: 16px; }
.lm-intro { font-size: 18px; line-height: 1.75; margin-bottom: 28px; color: var(--text); }
.lm-card { background: #f8fafc; border: 1px solid var(--border); border-left: 4px solid var(--primary); border-radius: 8px; padding: 20px 24px; margin: 24px 0; }
.lm-card-accent { border-left-color: var(--accent); background: linear-gradient(90deg, #fffbeb 0%, #fff 50%); }
.lm-card h3, .lm-card h4 { margin-top: 0; font-family: 'Inter', sans-serif; font-size: 15px; color: var(--primary); }
.lm-checklist { list-style: none; padding: 0; margin: 16px 0; }
.lm-checklist li { padding: 8px 0 8px 28px; position: relative; border-bottom: 1px solid #f1f5f9; }
.lm-checklist li:before { content: "✓"; position: absolute; left: 0; color: var(--secondary); font-weight: 700; }
.lm-tip { background: #ecfdf5; border-left: 4px solid var(--secondary); border-radius: 0 8px 8px 0; padding: 16px 20px; margin: 24px 0; font-size: 15px; }
.lm-tip strong { color: var(--secondary); }
.lm-warning { background: #fffbeb; border-left: 4px solid var(--accent); border-radius: 0 8px 8px 0; padding: 16px 20px; margin: 24px 0; }
.lm-steps { counter-reset: step; list-style: none; padding: 0; margin: 20px 0; }
.lm-steps li { counter-increment: step; padding: 14px 14px 14px 52px; position: relative; margin-bottom: 10px; background: #f8fafc; border-radius: 8px; }
.lm-steps li:before { content: counter(step); position: absolute; left: 14px; top: 14px; width: 26px; height: 26px; background: var(--primary); color: #fff; border-radius: 50%; text-align: center; line-height: 26px; font-weight: 700; font-size: 12px; }
.lm-stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin: 24px 0; }
.lm-stat { background: var(--bg); border-radius: 8px; padding: 16px; text-align: center; border-top: 3px solid var(--accent); }
.lm-stat strong { display: block; font-size: 26px; color: var(--primary); font-family: 'Inter', sans-serif; }
.lm-stat span { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }
.lm-quote { border-left: 4px solid var(--accent); padding: 12px 24px; margin: 28px 0; font-style: italic; font-size: 17px; color: var(--muted); }
.lm-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; font-family: 'Inter', sans-serif; }
.lm-table th { background: var(--primary); color: #fff; padding: 10px 12px; text-align: left; }
.lm-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); }
.lm-exercise { background: #f0fdf4; border: 2px dashed #86efac; border-radius: 10px; padding: 20px 24px; margin: 24px 0; }
.lm-exercise h4 { margin-top: 0; color: #15803d; font-family: 'Inter', sans-serif; }
.lm-footer { margin-top: 48px; padding-top: 20px; border-top: 2px solid var(--border); text-align: center; }
.lm-footer p { font-size: 14px; color: var(--muted); margin-bottom: 12px; }
.lm-footer-cta { display: inline-block; background: var(--primary); color: #fff !important; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 15px; }
@media print { .lm-page { page-break-after: always; box-shadow: none; margin: 0; } body { background: #fff; } }
CSS;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultDesignSystem(): array
    {
        return [
            'primary' => '#4f46e5',
            'secondary' => '#0ea5e9',
            'accent' => '#f59e0b',
            'background' => '#f8fafc',
            'text' => '#1e293b',
            'style_notes' => 'modern professional',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<int, array<string, mixed>>
     */
    protected function sortPages(array $pages): array
    {
        usort($pages, fn ($a, $b) => ((int) ($a['page'] ?? 0)) <=> ((int) ($b['page'] ?? 0)));

        return array_values($pages);
    }

    protected function syncThankYouDownload(Campaign $campaign, string $title): void
    {
        $thankyou = $campaign->pages()->where('page_type', 'thankyou')->first();
        if (! $thankyou) {
            return;
        }

        $content = $thankyou->content ?? [];
        unset($content['download_url']);
        $content['download_label'] = $title;
        $content['headline'] = $content['headline'] ?? 'Congrats! Click the link below to download your free gift…';
        $content['download_cta'] = $content['download_cta'] ?? 'DOWNLOAD NOW';
        $thankyou->update(['content' => $content]);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function upsertLeadMagnetPage(Campaign $campaign, array $content): void
    {
        CampaignPage::query()->updateOrCreate(
            ['campaign_id' => $campaign->id, 'page_type' => 'lead_magnet'],
            ['slug' => $campaign->slug.'-lead-magnet', 'content' => $content, 'version' => 1]
        );
    }
}
