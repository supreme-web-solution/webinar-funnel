<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignBonus;
use App\Services\Ai\OpenRouterService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BonusGeneratorService
{
    public const ALLOWED_TYPES = ['ebook', 'mini_course'];

    public const SUGGESTION_COUNT = 3;

    public function __construct(
        protected OpenRouterService $openRouter,
        protected CampaignKnowledgeBuilderService $knowledge,
        protected TrackedLinkService $trackedLinks,
        protected LeadMagnetPdfService $pdf,
        protected CampaignBonusPresenterService $presenter,
    ) {}

    /**
     * @return array{ok: bool, suggestions: array<int, array<string, mixed>>, error: string|null}
     */
    public function suggest(Campaign $campaign, string $bonusType): array
    {
        if (! in_array($bonusType, self::ALLOWED_TYPES, true)) {
            return ['ok' => false, 'suggestions' => [], 'error' => 'Invalid bonus type.'];
        }

        $ctx = $this->knowledge->contextForGeneration($campaign);
        $product = $this->resolveProductLabel($ctx);
        $concepts = collect($ctx['pass2']['bonus_concepts'] ?? [])
            ->filter(fn ($c) => is_array($c) && ($c['bonus_type'] ?? '') === $bonusType)
            ->values()
            ->all();

        $concepts = array_slice($concepts, 0, self::SUGGESTION_COUNT);
        $concepts = array_map(fn ($c) => $this->normalizeConcept(is_array($c) ? $c : [], $bonusType, $ctx), $concepts);

        if (count($concepts) < self::SUGGESTION_COUNT) {
            $typeLabel = $bonusType === 'mini_course' ? 'mini-course (multi-lesson slide deck)' : 'ebook (PDF-ready guide)';
            $json = $this->openRouter->chatJson([
                [
                    'role' => 'system',
                    'content' => 'Return ONLY JSON {"bonuses":[{"id":"b1","bonus_type":"'.$bonusType.'","title":"","promise":"","why_it_helps":""}]} with exactly '.self::SUGGESTION_COUNT.' distinct, high-value '.$typeLabel.' concepts that stack with the affiliate product "'.$product.'". Every title MUST name "'.$product.'" OR its unique mechanism from the context. promise must explain how this bonus helps buyers succeed WITH that product — NOT generic affiliate marketing advice (no standalone email/traffic/squeeze-page courses).',
                ],
                ['role' => 'user', 'content' => json_encode($ctx, JSON_PRETTY_PRINT)],
            ]);
            $generated = $json['ok'] ? ($json['data']['bonuses'] ?? []) : [];
            if (is_array($generated) && $generated !== []) {
                $concepts = array_map(
                    fn ($c) => $this->normalizeConcept(is_array($c) ? $c : [], $bonusType, $ctx),
                    array_slice($generated, 0, self::SUGGESTION_COUNT),
                );
            }
        }

        if ($concepts === []) {
            $typeLabel = $bonusType === 'mini_course' ? 'mini-course' : 'ebook';
            $json = $this->openRouter->chatJson([
                [
                    'role' => 'system',
                    'content' => 'Return ONLY JSON {"bonuses":[{"id":"b1","bonus_type":"'.$bonusType.'","title":"","promise":"","why_it_helps":""}]} with exactly '.self::SUGGESTION_COUNT.' distinct '.$typeLabel.' concepts tied to "'.$product.'". Titles must reference the product or its mechanism.',
                ],
                ['role' => 'user', 'content' => json_encode($ctx, JSON_PRETTY_PRINT)],
            ]);
            $generated = $json['ok'] ? ($json['data']['bonuses'] ?? []) : [];
            $concepts = array_map(
                fn ($c) => $this->normalizeConcept(is_array($c) ? $c : [], $bonusType, $ctx),
                array_slice(is_array($generated) ? $generated : [], 0, self::SUGGESTION_COUNT),
            );
        }

        foreach ($concepts as $i => $concept) {
            if (! is_array($concept)) {
                continue;
            }
            $concepts[$i]['id'] = (string) ($concept['id'] ?? 'bonus_'.($i + 1));
            $concepts[$i]['bonus_type'] = $bonusType;
        }

        $meta = $campaign->meta ?? [];
        $meta['bonus_type'] = $bonusType;
        $meta['bonus_suggestions'] = $concepts;
        $campaign->update(['meta' => $meta]);

        return ['ok' => true, 'suggestions' => $concepts, 'error' => null];
    }

    /**
     * @return array{ok: bool, bonus: CampaignBonus|null, error: string|null}
     */
    public function generate(Campaign $campaign, string $selectedId, string $bonusType): array
    {
        if (! in_array($bonusType, self::ALLOWED_TYPES, true)) {
            return ['ok' => false, 'bonus' => null, 'error' => 'Invalid bonus type.'];
        }

        $suggestions = collect($campaign->meta['bonus_suggestions'] ?? []);
        $selected = $suggestions->firstWhere('id', $selectedId);

        if (! is_array($selected)) {
            return ['ok' => false, 'bonus' => null, 'error' => 'Selected bonus not found. Run suggest first.'];
        }

        $existing = CampaignBonus::query()
            ->where('campaign_id', $campaign->id)
            ->where('meta->selected_id', $selectedId)
            ->where('status', 'ready')
            ->first();

        if ($existing) {
            return ['ok' => true, 'bonus' => $existing, 'error' => null];
        }

        CampaignBonus::query()
            ->where('campaign_id', $campaign->id)
            ->where('meta->selected_id', $selectedId)
            ->delete();

        $ctx = $this->knowledge->contextForGeneration($campaign);
        $affiliate = (string) ($campaign->affiliate_link ?? '#');
        $cloaked = $this->trackedLinks->createForCampaign($campaign, $affiliate, 'Bonus CTA');
        $affiliateUrl = $cloaked->publicUrl();

        $result = $bonusType === 'mini_course'
            ? $this->generateMiniCourse($campaign, $selected, $ctx, $affiliateUrl)
            : $this->generateEbook($campaign, $selected, $ctx, $affiliateUrl);

        if (! $result['ok']) {
            return ['ok' => false, 'bonus' => null, 'error' => $result['error'] ?? 'Bonus generation failed.'];
        }

        $coverGradient = $bonusType === 'mini_course'
            ? 'linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%)'
            : 'linear-gradient(135deg,#dc2626 0%,#991b1b 100%)';

        $bonus = CampaignBonus::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $campaign->user_id,
            'title' => $result['title'],
            'bonus_type' => $bonusType,
            'content' => $result['summary'],
            'status' => 'ready',
            'meta' => [
                'selected_id' => $selectedId,
                'subtitle' => $result['subtitle'] ?? '',
                'download_url' => $result['download_url'] ?? null,
                'download_path' => $result['download_path'] ?? null,
                'pdf_path' => $result['pdf_path'] ?? null,
                'pages' => $result['pages'] ?? [],
                'slides' => $result['slides'] ?? [],
                'viewer_url' => $result['viewer_url'] ?? null,
                'value_label' => $result['value_label'] ?? null,
                'cover_gradient' => $coverGradient,
            ],
        ]);

        if ($bonusType === 'mini_course') {
            $campaign->loadMissing('user');
            $username = $campaign->user?->username ?? 'user';
            $meta = $bonus->meta ?? [];
            $meta['viewer_url'] = route('public.campaign.bonus.viewer', [
                'username' => $username,
                'slug' => $campaign->slug,
                'bonusUuid' => $bonus->uuid,
            ]);
            $bonus->update(['meta' => $meta]);
        }

        $this->refreshBonusPage($campaign, $bonus->uuid);

        return ['ok' => true, 'bonus' => $bonus, 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    protected function normalizeConcept(array $row, string $bonusType, array $ctx): array
    {
        $row = $this->anchorConceptToOffer($row, $bonusType, $ctx);
        $product = $this->resolveProductLabel($ctx);
        $promise = trim((string) ($row['promise'] ?? $row['why_it_helps'] ?? ''));

        return [
            ...$row,
            'bonus_type' => $bonusType,
            'promise' => $promise,
            'why_it_helps' => $promise,
            'offer_anchor' => (string) ($row['offer_anchor'] ?? "Bonus for: {$product}"),
            'from_knowledge' => (bool) ($row['from_knowledge'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    protected function anchorConceptToOffer(array $row, string $bonusType, array $ctx): array
    {
        $product = $this->resolveProductLabel($ctx);
        $mechanism = trim((string) ($ctx['pass1']['unique_mechanism'] ?? ''));
        $title = trim((string) ($row['title'] ?? 'Bonus'));
        $promise = trim((string) ($row['promise'] ?? $row['why_it_helps'] ?? ''));

        if (! $this->mentionsOffer($title.' '.$promise, $ctx)) {
            $topic = preg_replace('/^(The|A|An)\s+/i', '', $title) ?? $title;
            $shortProduct = Str::limit($product, 48, '');
            $title = $bonusType === 'mini_course'
                ? "Mini-Course: {$topic} (with {$shortProduct})"
                : "{$shortProduct}: {$topic}";

            if ($promise === '') {
                $promise = "Helps buyers get faster results with {$product}.";
            } elseif (! str_contains(strtolower($promise), strtolower($product))) {
                $promise = "For {$product} buyers: {$promise}";
            }

            if ($mechanism !== '' && ! str_contains(strtolower($promise), strtolower($mechanism))) {
                $promise .= ' Tied to '.$mechanism.'.';
            }
        }

        return [
            ...$row,
            'title' => $title,
            'promise' => $promise,
            'offer_anchor' => "Bonus for: {$product}",
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

    /**
     * @param  array<string, mixed>  $selected
     * @param  array<string, mixed>  $ctx
     * @return array{ok: bool, title?: string, summary?: string, pages?: array, download_url?: string, download_path?: string, error?: string}
     */
    protected function generateEbook(Campaign $campaign, array $selected, array $ctx, string $affiliateUrl): array
    {
        $json = $this->openRouter->chatJson([
            [
                'role' => 'system',
                'content' => 'Write a premium affiliate bonus ebook. Return ONLY JSON {"title":"","subtitle":"","pages":[{"page":1,"title":"","body_html":""}]} with 8-10 pages.

RULES:
- Each page: 400+ words of niche-specific, actionable content
- Use CSS classes: doc-intro, doc-section, doc-card, doc-checklist, doc-tip, doc-steps, doc-pullquote
- body_html = HTML fragment only (no html/head/body)
- Write like a professional publisher — clear hierarchy, concrete examples, zero generic AI phrases
- Page 1 = compelling introduction; middle pages = tactics; last page = action plan + soft CTA',
            ],
            [
                'role' => 'user',
                'content' => json_encode(['bonus_concept' => $selected, 'context' => $ctx], JSON_PRETTY_PRINT),
            ],
        ]);

        if (! $json['ok'] || ! is_array($json['data'])) {
            return ['ok' => false, 'error' => $json['error'] ?? 'Ebook generation failed.'];
        }

        $data = $json['data'];
        $title = (string) ($data['title'] ?? $selected['title'] ?? 'Bonus Guide');
        $subtitle = (string) ($data['subtitle'] ?? $selected['promise'] ?? '');
        $pages = is_array($data['pages'] ?? null) ? $data['pages'] : [];

        $html = $this->buildEbookHtml($title, $subtitle, $pages, $affiliateUrl);
        $path = "campaigns/{$campaign->uuid}/bonus-".Str::slug($title).'.html';
        Storage::disk('public')->put($path, $html);

        $pdfPath = preg_replace('/\.html$/', '.pdf', $path) ?: $path.'.pdf';
        try {
            $this->pdf->generateAndStore($html, $pdfPath);
        } catch (\Throwable) {
            $pdfPath = null;
        }

        $summary = collect($pages)->take(2)->map(fn ($p) => ($p['title'] ?? '').': '.strip_tags((string) ($p['body_html'] ?? '')))->join("\n\n");

        return [
            'ok' => true,
            'title' => $title,
            'subtitle' => $subtitle,
            'summary' => Str::limit($summary, 2000),
            'pages' => $pages,
            'download_path' => $path,
            'pdf_path' => $pdfPath,
            'download_url' => Storage::disk('public')->url($path),
            'viewer_url' => Storage::disk('public')->url($path),
            'value_label' => '$27 value',
        ];
    }

    /**
     * @param  array<string, mixed>  $selected
     * @param  array<string, mixed>  $ctx
     * @return array{ok: bool, title?: string, summary?: string, slides?: array, download_url?: string, download_path?: string, viewer_url?: string, error?: string}
     */
    protected function generateMiniCourse(Campaign $campaign, array $selected, array $ctx, string $affiliateUrl): array
    {
        $json = $this->openRouter->chatJson([
            [
                'role' => 'system',
                'content' => 'Write a premium mini-course as slide deck lessons. Return ONLY JSON {"title":"","subtitle":"","slides":[{"slide":1,"title":"","subtitle":"","body_html":"","takeaway":""}]} with 10-14 slides.

RULES:
- Slide 1 = title/overview; slides 2-N = one focused lesson each; last slide = recap + CTA
- Each lesson slide: 250-400 words in body_html, one clear takeaway sentence
- Use CSS classes: slide-lead, slide-card, slide-checklist, slide-highlight, slide-steps
- body_html = HTML fragment only
- Teach one tactic per slide — specific to the offer niche, not generic marketing advice',
            ],
            [
                'role' => 'user',
                'content' => json_encode(['bonus_concept' => $selected, 'context' => $ctx], JSON_PRETTY_PRINT),
            ],
        ]);

        if (! $json['ok'] || ! is_array($json['data'])) {
            return ['ok' => false, 'error' => $json['error'] ?? 'Mini-course generation failed.'];
        }

        $data = $json['data'];
        $title = (string) ($data['title'] ?? $selected['title'] ?? 'Mini Course');
        $subtitle = (string) ($data['subtitle'] ?? $selected['promise'] ?? '');
        $slides = is_array($data['slides'] ?? null) ? $data['slides'] : [];

        $html = $this->buildCourseViewerHtml($title, $subtitle, $slides, $affiliateUrl);
        $path = "campaigns/{$campaign->uuid}/course-".Str::slug($title).'.html';
        Storage::disk('public')->put($path, $html);

        $summary = collect($slides)->take(3)->map(fn ($s) => ($s['title'] ?? '').': '.($s['takeaway'] ?? ''))->join("\n");

        return [
            'ok' => true,
            'title' => $title,
            'subtitle' => $subtitle,
            'summary' => Str::limit($summary, 2000),
            'slides' => $slides,
            'download_path' => $path,
            'download_url' => Storage::disk('public')->url($path),
            'viewer_url' => Storage::disk('public')->url($path),
            'value_label' => '$97 value',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     */
    protected function buildEbookHtml(string $title, string $subtitle, array $pages, string $affiliateUrl): string
    {
        $css = $this->documentStyles('#1e3a5f', '#0d9488', '#d97706');

        usort($pages, fn ($a, $b) => ((int) ($a['page'] ?? 0)) <=> ((int) ($b['page'] ?? 0)));

        $cover = '<section class="doc-page doc-cover"><div class="doc-cover-inner">'
            .'<p class="doc-label">Exclusive Bonus</p>'
            .'<h1>'.e($title).'</h1>'
            .($subtitle !== '' ? '<p class="doc-subtitle">'.e($subtitle).'</p>' : '')
            .'<p class="doc-meta">'.count($pages).' chapters · Professional guide</p>'
            .'</div></section>';

        $body = $cover;
        foreach ($pages as $p) {
            $num = (int) ($p['page'] ?? 0);
            $body .= '<section class="doc-page">';
            $body .= '<header class="doc-header"><span class="doc-chapter">Chapter '.$num.'</span>';
            $body .= '<h2>'.e($p['title'] ?? "Chapter {$num}").'</h2></header>';
            $body .= '<article class="doc-body">'.($p['body_html'] ?? '').'</article>';
            $body .= '</section>';
        }

        $body .= '<section class="doc-page doc-back"><p>Ready for the next step?</p>'
            .'<a class="doc-cta" href="'.e($affiliateUrl).'">Get the full solution →</a></section>';

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.e($title).'</title><style>'.$css.'</style></head><body>'.$body.'</body></html>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $slides
     */
    protected function buildCourseViewerHtml(string $title, string $subtitle, array $slides, string $affiliateUrl): string
    {
        $css = $this->courseStyles();

        usort($slides, fn ($a, $b) => ((int) ($a['slide'] ?? 0)) <=> ((int) ($b['slide'] ?? 0)));

        $lessonCount = count($slides);
        $totalSlides = 2 + $lessonCount; // cover + outline + lessons

        $outlineItems = '';
        foreach ($slides as $i => $s) {
            $outlineItems .= '<li><span>'.($i + 1).'</span> '.e($s['title'] ?? 'Lesson '.($i + 1)).'</li>';
        }

        $coverLead = $subtitle !== ''
            ? e($subtitle)
            : 'Swipe or use the arrows below to explore this mini course.';

        $slideHtml = '<section class="course-slide slide-cover active" data-index="0">'
            .'<div class="cover-art" aria-hidden="true"></div>'
            .'<span class="preview-badge"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg> Course preview</span>'
            .'<h2 class="cover-title">'.e($title).'</h2>'
            .'<p class="cover-lead">'.$coverLead.'</p>'
            .'</section>';

        $slideHtml .= '<section class="course-slide" data-index="1">'
            .'<div class="content-card">'
            .'<p class="eyebrow">Structure</p>'
            .'<h2 class="slide-heading">Course outline</h2>'
            .'<p class="slide-meta">'.$lessonCount.' modules</p>'
            .'<ul class="outline-list">'.$outlineItems.'</ul>'
            .'</div></section>';

        foreach ($slides as $i => $s) {
            $num = (int) ($s['slide'] ?? ($i + 1));
            $idx = $i + 2;
            $slideHtml .= '<section class="course-slide" data-index="'.$idx.'">';
            $slideHtml .= '<div class="content-card">';
            $slideHtml .= '<p class="lesson-label">Lesson '.$num.'</p>';
            $slideHtml .= '<h2 class="slide-heading">'.e($s['title'] ?? "Lesson {$num}").'</h2>';
            if (! empty($s['subtitle'])) {
                $slideHtml .= '<p class="slide-meta">'.e($s['subtitle']).'</p>';
            }
            $slideHtml .= '<div class="slide-body">'.($s['body_html'] ?? '').'</div>';
            if (! empty($s['takeaway'])) {
                $slideHtml .= '<div class="takeaway"><strong>Takeaway:</strong> '.e($s['takeaway']).'</div>';
            }
            $slideHtml .= '</div></section>';
        }

        $dotsHtml = '';
        for ($d = 0; $d < $totalSlides; $d++) {
            $dotsHtml .= '<span class="dot'.($d === 0 ? ' active' : '').'" data-goto="'.$d.'"></span>';
        }

        $js = <<<'JS'
let current = 0;
const slides = document.querySelectorAll('.course-slide');
const counter = document.getElementById('slide-counter');
const progress = document.getElementById('progress-fill');
const dots = document.querySelectorAll('.dot');
const prevBtn = document.getElementById('prev');
const nextBtn = document.getElementById('next');
const total = slides.length;

function show(i) {
  current = Math.max(0, Math.min(i, total - 1));
  slides.forEach((s, idx) => s.classList.toggle('active', idx === current));
  dots.forEach((d, idx) => d.classList.toggle('active', idx === current));
  if (counter) counter.textContent = (current + 1) + ' / ' + total;
  if (progress) progress.style.width = ((current + 1) / total * 100) + '%';
  if (prevBtn) prevBtn.disabled = current === 0;
  if (nextBtn) nextBtn.disabled = current >= total - 1;
}
prevBtn?.addEventListener('click', () => show(current - 1));
nextBtn?.addEventListener('click', () => show(current + 1));
dots.forEach(d => d.addEventListener('click', () => show(parseInt(d.dataset.goto || '0', 10))));
document.addEventListener('keydown', (e) => {
  if (e.key === 'ArrowRight' || e.key === ' ') { e.preventDefault(); show(current + 1); }
  if (e.key === 'ArrowLeft') { e.preventDefault(); show(current - 1); }
});
let touchStartX = 0;
document.addEventListener('touchstart', (e) => { touchStartX = e.changedTouches[0].screenX; }, { passive: true });
document.addEventListener('touchend', (e) => {
  const diff = e.changedTouches[0].screenX - touchStartX;
  if (Math.abs(diff) > 50) show(current + (diff < 0 ? 1 : -1));
}, { passive: true });
show(0);
JS;

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.e($title).'</title><style>'.$css.'</style></head><body>'
            .'<div class="course-app">'
            .'<div class="progress-track"><div class="progress-fill" id="progress-fill"></div></div>'
            .'<header class="course-header">'
            .'<div class="brand"><div class="brand-icon">C</div><div><p class="brand-title">'.e($title).'</p>'
            .'<p class="brand-meta">'.$lessonCount.' modules</p></div></div>'
            .'<span id="slide-counter" class="page-badge">1 / '.$totalSlides.'</span>'
            .'</header>'
            .'<main class="course-main">'.$slideHtml.'</main>'
            .'<footer class="course-footer">'
            .'<button type="button" class="btn-ghost" id="prev" disabled><span aria-hidden="true">‹</span> Previous</button>'
            .'<div class="dots" id="dots">'.$dotsHtml.'</div>'
            .'<button type="button" class="btn-primary" id="next">Next <span aria-hidden="true">›</span></button>'
            .'</footer></div>'
            .'<script>'.$js.'</script></body></html>';
    }

    protected function courseStyles(): string
    {
        return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
:root {
  --violet: #7c3aed;
  --violet-light: #ede9fe;
  --violet-dark: #6d28d9;
  --text: #0f172a;
  --muted: #64748b;
  --border: #e2e8f0;
  --bg: #ffffff;
  --surface: #f8fafc;
}
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; -webkit-font-smoothing: antialiased; }
.course-app { max-width: 720px; margin: 0 auto; min-height: 100vh; display: flex; flex-direction: column; background: var(--bg); }
.progress-track { height: 3px; background: var(--violet-light); width: 100%; }
.progress-fill { height: 100%; background: var(--violet); width: 0%; transition: width 0.35s ease; }
.course-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 20px; border-bottom: 1px solid var(--border); background: var(--bg); }
.brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
.brand-icon { width: 36px; height: 36px; border-radius: 10px; background: var(--violet); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; flex-shrink: 0; }
.brand-title { margin: 0; font-size: 14px; font-weight: 600; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
.brand-meta { margin: 2px 0 0; font-size: 12px; color: var(--muted); }
.page-badge { flex-shrink: 0; font-size: 12px; font-weight: 500; color: var(--muted); background: var(--surface); border: 1px solid var(--border); padding: 6px 12px; border-radius: 999px; }
.course-main { flex: 1; padding: 32px 20px 24px; display: flex; align-items: center; justify-content: center; }
.course-slide { display: none; width: 100%; animation: fadeUp 0.35s ease; }
.course-slide.active { display: block; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
.slide-cover { text-align: center; max-width: 420px; margin: 0 auto; }
.cover-art {
  width: 100%; max-width: 280px; aspect-ratio: 3/4; margin: 0 auto 24px; border-radius: 16px;
  background: linear-gradient(145deg, #991b1b 0%, #450a0a 100%);
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
  position: relative; overflow: hidden;
}
.cover-art::before {
  content: ''; position: absolute; inset: 0; opacity: 0.35;
  background: repeating-linear-gradient(45deg, transparent, transparent 8px, rgba(255,255,255,0.08) 8px, rgba(255,255,255,0.08) 16px);
}
.preview-badge {
  display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500;
  color: var(--violet-dark); background: var(--violet-light); padding: 6px 12px; border-radius: 999px; margin-bottom: 16px;
}
.cover-title { font-size: 28px; font-weight: 700; margin: 0 0 12px; line-height: 1.2; letter-spacing: -0.02em; }
.cover-lead { font-size: 15px; color: var(--muted); line-height: 1.6; margin: 0; max-width: 360px; margin-inline: auto; }
.content-card {
  background: var(--bg); border: 1px solid var(--border); border-radius: 16px;
  padding: 28px 24px; box-shadow: 0 4px 24px rgba(15, 23, 42, 0.04); width: 100%;
}
.eyebrow { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--violet); margin: 0 0 8px; }
.lesson-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--violet); margin: 0 0 8px; }
.slide-heading { font-size: 24px; font-weight: 700; margin: 0 0 8px; line-height: 1.25; letter-spacing: -0.02em; }
.slide-meta { font-size: 14px; color: var(--muted); margin: 0 0 20px; }
.outline-list { list-style: none; padding: 0; margin: 8px 0 0; display: flex; flex-direction: column; gap: 10px; }
.outline-list li {
  display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: var(--surface);
  border-radius: 12px; font-size: 14px; font-weight: 500; color: var(--text);
}
.outline-list li span {
  width: 28px; height: 28px; border-radius: 999px; background: var(--violet); color: #fff;
  display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0;
}
.slide-body { font-size: 15px; line-height: 1.75; color: #334155; }
.slide-lead { font-size: 17px; line-height: 1.7; margin-bottom: 20px; color: var(--muted); }
.slide-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; margin: 16px 0; }
.slide-checklist { list-style: none; padding: 0; margin: 12px 0; }
.slide-checklist li { padding: 8px 0 8px 24px; position: relative; border-bottom: 1px solid #f1f5f9; }
.slide-checklist li:before { content: "✓"; position: absolute; left: 0; color: var(--violet); font-weight: 700; }
.slide-highlight { background: var(--violet-light); border-left: 3px solid var(--violet); padding: 12px 16px; margin: 16px 0; border-radius: 0 10px 10px 0; }
.slide-steps { counter-reset: ls; list-style: none; padding: 0; margin: 16px 0; }
.slide-steps li { counter-increment: ls; padding: 12px 12px 12px 44px; position: relative; margin-bottom: 8px; background: var(--surface); border-radius: 10px; }
.slide-steps li:before { content: counter(ls); position: absolute; left: 12px; top: 12px; width: 24px; height: 24px; background: var(--violet); color: #fff; border-radius: 50%; text-align: center; line-height: 24px; font-size: 11px; font-weight: 700; }
.takeaway { margin-top: 24px; padding: 14px 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; font-size: 14px; color: #1e40af; }
.course-footer {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 16px 20px 24px; border-top: 1px solid var(--border); background: var(--bg);
}
.btn-ghost, .btn-primary {
  display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; font-size: 14px; font-weight: 600;
  border-radius: 999px; cursor: pointer; font-family: inherit; transition: opacity 0.2s, background 0.2s;
}
.btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--muted); }
.btn-ghost:hover:not(:disabled) { background: var(--surface); color: var(--text); }
.btn-ghost:disabled { opacity: 0.35; cursor: not-allowed; }
.btn-primary { background: var(--violet); border: none; color: #fff; box-shadow: 0 4px 14px rgba(124, 58, 237, 0.35); }
.btn-primary:hover:not(:disabled) { background: var(--violet-dark); }
.btn-primary:disabled { opacity: 0.35; cursor: not-allowed; box-shadow: none; }
.dots { display: flex; align-items: center; justify-content: center; gap: 6px; flex: 1; max-width: 200px; }
.dot { width: 8px; height: 8px; border-radius: 999px; background: #cbd5e1; cursor: pointer; transition: all 0.25s ease; }
.dot.active { width: 28px; background: var(--violet); }
@media (max-width: 480px) {
  .brand-title { max-width: 140px; }
  .cover-title { font-size: 24px; }
  .btn-ghost span, .btn-primary span { display: none; }
}
CSS;
    }

    protected function documentStyles(string $primary, string $secondary, string $accent): string
    {
        return <<<CSS
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&display=swap');
:root { --primary: {$primary}; --secondary: {$secondary}; --accent: {$accent}; --text: #1a2332; --muted: #5c6b7a; --border: #e2e8f0; }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Inter', system-ui, sans-serif; color: var(--text); background: #eef1f5; line-height: 1.7; font-size: 15px; }
.doc-page { max-width: 720px; margin: 0 auto 24px; padding: 56px 64px; background: #fff; min-height: 90vh; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.doc-cover { display: flex; align-items: center; justify-content: center; text-align: center; background: linear-gradient(160deg, var(--primary) 0%, #2d4a6f 100%); color: #fff; border-radius: 2px; }
.doc-cover-inner { max-width: 480px; }
.doc-label { text-transform: uppercase; letter-spacing: 0.14em; font-size: 11px; font-weight: 600; opacity: 0.85; margin: 0; }
.doc-cover h1 { font-family: 'Source Serif 4', Georgia, serif; font-size: 34px; font-weight: 700; margin: 20px 0 12px; line-height: 1.2; }
.doc-subtitle { font-size: 17px; opacity: 0.92; margin: 0 0 28px; font-weight: 400; }
.doc-meta { font-size: 13px; opacity: 0.75; }
.doc-header { border-bottom: 2px solid var(--primary); padding-bottom: 16px; margin-bottom: 32px; }
.doc-chapter { font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); font-weight: 600; }
.doc-header h2 { font-family: 'Source Serif 4', Georgia, serif; font-size: 28px; margin: 8px 0 0; color: var(--primary); font-weight: 700; }
.doc-body { font-family: 'Source Serif 4', Georgia, serif; font-size: 16px; }
.doc-intro { font-size: 18px; line-height: 1.75; margin-bottom: 28px; color: var(--text); }
.doc-section { margin: 28px 0; }
.doc-section h3 { font-family: 'Inter', sans-serif; font-size: 18px; font-weight: 600; color: var(--primary); margin: 0 0 12px; }
.doc-card { background: #f8fafc; border: 1px solid var(--border); border-left: 4px solid var(--secondary); border-radius: 8px; padding: 20px 24px; margin: 24px 0; }
.doc-card h4 { margin: 0 0 8px; font-family: 'Inter', sans-serif; font-size: 15px; color: var(--primary); }
.doc-checklist { list-style: none; padding: 0; margin: 16px 0; }
.doc-checklist li { padding: 8px 0 8px 28px; position: relative; border-bottom: 1px solid #f1f5f9; }
.doc-checklist li:before { content: "✓"; position: absolute; left: 0; color: var(--secondary); font-weight: 700; }
.doc-tip { background: #ecfdf5; border-left: 4px solid var(--secondary); padding: 16px 20px; margin: 24px 0; border-radius: 0 8px 8px 0; font-size: 15px; }
.doc-steps { counter-reset: st; list-style: none; padding: 0; margin: 20px 0; }
.doc-steps li { counter-increment: st; padding: 14px 14px 14px 52px; position: relative; margin-bottom: 10px; background: #f8fafc; border-radius: 8px; }
.doc-steps li:before { content: counter(st); position: absolute; left: 14px; top: 14px; width: 26px; height: 26px; background: var(--primary); color: #fff; border-radius: 50%; text-align: center; line-height: 26px; font-size: 12px; font-weight: 700; }
.doc-pullquote { border-left: 4px solid var(--accent); padding: 12px 24px; margin: 28px 0; font-style: italic; font-size: 17px; color: var(--muted); }
.doc-back { text-align: center; padding: 80px 64px; }
.doc-cta { display: inline-block; background: var(--primary); color: #fff !important; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 15px; margin-top: 16px; }
@media print { .doc-page { box-shadow: none; page-break-after: always; margin: 0; } body { background: #fff; } }
CSS;
    }

    protected function refreshBonusPage(Campaign $campaign, ?string $autoFeatureUuid = null): void
    {
        $this->presenter->syncBonusPage($campaign, $autoFeatureUuid);
        $this->rebuildCourseViewerFiles($campaign);
    }

    /**
     * Rewrite stored mini-course HTML from saved slide meta (no AI).
     */
    public function rebuildCourseViewerFiles(Campaign $campaign): void
    {
        $affiliate = (string) ($campaign->affiliate_link ?? '#');

        $campaign->bonuses()
            ->where('bonus_type', 'mini_course')
            ->where('status', 'ready')
            ->each(function (CampaignBonus $bonus) use ($campaign, $affiliate): void {
                $meta = is_array($bonus->meta) ? $bonus->meta : [];
                $slides = $meta['slides'] ?? [];
                if (! is_array($slides) || $slides === []) {
                    return;
                }

                $html = $this->buildCourseViewerHtml(
                    $bonus->title,
                    (string) ($meta['subtitle'] ?? ''),
                    $slides,
                    $affiliate,
                );

                $path = (string) ($meta['download_path'] ?? '');
                if ($path === '') {
                    $path = 'campaigns/'.$campaign->uuid.'/course-'.Str::slug($bonus->title).'.html';
                }

                Storage::disk('public')->put($path, $html);

                if ($path !== ($meta['download_path'] ?? null)) {
                    $meta['download_path'] = $path;
                    $bonus->update(['meta' => $meta]);
                }
            });
    }
}
