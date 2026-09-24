<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignBonus;
use Illuminate\Support\Str;

class CampaignBonusPresenterService
{
    public function __construct(
        protected LeadMagnetPdfService $pdf,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forPublic(Campaign $campaign, CampaignBonus $bonus, string $username): array
    {
        $meta = is_array($bonus->meta) ? $bonus->meta : [];
        $type = (string) $bonus->bonus_type;
        $slug = $campaign->slug;

        $viewerUrl = route('public.campaign.bonus.viewer', [
            'username' => $username,
            'slug' => $slug,
            'bonusUuid' => $bonus->uuid,
        ]);

        $downloadUrl = null;
        if ($type === 'ebook') {
            $downloadUrl = route('public.campaign.bonus.download', [
                'username' => $username,
                'slug' => $slug,
                'bonusUuid' => $bonus->uuid,
            ]);
        } elseif ($type === 'mini_course') {
            $downloadUrl = $viewerUrl;
        } elseif ($type === 'mini_app' && ! empty($meta['app_url'])) {
            $downloadUrl = (string) $meta['app_url'];
        }

        $features = $this->featuresFor($bonus, $meta);

        return [
            'uuid' => $bonus->uuid,
            'title' => $bonus->title,
            'bonus_type' => $type,
            'type_label' => $this->typeLabel($type),
            'description' => Str::limit(strip_tags((string) ($bonus->content ?? '')), 280),
            'value_label' => (string) ($meta['value_label'] ?? $this->defaultValueLabel($type)),
            'cover_gradient' => (string) ($meta['cover_gradient'] ?? $this->defaultGradient($type)),
            'features' => $features,
            'viewer_url' => $viewerUrl,
            'download_url' => $downloadUrl,
            'cta_label' => $this->ctaLabel($type),
            'secondary_cta_label' => $type === 'mini_app' ? 'Login ↗' : null,
            'page_count' => count($meta['pages'] ?? []),
            'slide_count' => count($meta['slides'] ?? []),
            'subtitle' => (string) ($meta['subtitle'] ?? ''),
        ];
    }

    /**
     * Keep bonus funnel page content in sync with campaign bonuses.
     */
    public function syncBonusPage(Campaign $campaign, ?string $autoFeatureUuid = null): void
    {
        $bonusPage = $campaign->pages()->where('page_type', 'bonus')->first();
        if (! $bonusPage) {
            return;
        }

        $campaign->loadMissing('user');
        $username = $campaign->user?->username ?? 'user';
        $content = is_array($bonusPage->content) ? $bonusPage->content : [];

        $featured = is_array($content['featured_bonus_uuids'] ?? null) ? $content['featured_bonus_uuids'] : [];
        if ($autoFeatureUuid && ! in_array($autoFeatureUuid, $featured, true)) {
            $featured[] = $autoFeatureUuid;
        }

        if ($featured === []) {
            $featured = $campaign->bonuses()->where('status', 'ready')->pluck('uuid')->all();
        }

        $content['featured_bonus_uuids'] = array_values($featured);
        $content['hero_label'] = $content['hero_label'] ?? 'YOUR BONUSES';
        $content['brand_color'] = $content['brand_color'] ?? '#ea580c';
        $content['bonuses'] = $this->featuredForPage($campaign, $content, $username);
        $content['total_value_label'] = $this->totalValueLabel($content['bonuses']);

        $bonusPage->update(['content' => $content]);
    }

    /**
     * @param  list<array<string, mixed>>  $bonuses
     */
    protected function totalValueLabel(array $bonuses): string
    {
        $total = 0;
        foreach ($bonuses as $bonus) {
            if (preg_match('/\$(\d+)/', (string) ($bonus['value_label'] ?? ''), $m)) {
                $total += (int) $m[1];
            }
        }

        if ($total <= 0) {
            return count($bonuses).' bonus'.(count($bonuses) === 1 ? '' : 'es');
        }

        return count($bonuses).' bonuses · $'.$total.' total value';
    }

    /**
     * Resolve featured bonuses from this campaign + the user's bonus library.
     *
     * @return list<array<string, mixed>>
     */
    public function featuredForPage(Campaign $campaign, array $pageContent, string $username): array
    {
        $featured = $pageContent['featured_bonus_uuids'] ?? [];
        if (! is_array($featured) || $featured === []) {
            $featured = $campaign->bonuses()->where('status', 'ready')->pluck('uuid')->all();
        }

        $featured = array_values(array_unique(array_map('strval', $featured)));

        if ($featured === []) {
            return [];
        }

        return CampaignBonus::query()
            ->where('user_id', $campaign->user_id)
            ->whereIn('uuid', $featured)
            ->where('status', 'ready')
            ->get()
            ->sortBy(fn (CampaignBonus $b) => array_search($b->uuid, $featured, true))
            ->map(fn (CampaignBonus $b) => $this->forPublic($campaign, $b, $username))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function libraryForUser(int $userId, ?string $search = null, int $limit = 50, ?string $since = null): array
    {
        $query = CampaignBonus::query()
            ->where('user_id', $userId)
            ->where('status', 'ready')
            ->with('campaign:id,name')
            ->latest();

        if (is_string($search) && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('bonus_type', 'like', $term);
            });
        }

        if (is_string($since) && $since !== '' && $since !== 'all') {
            $days = match ($since) {
                '7d' => 7,
                '30d' => 30,
                '90d' => 90,
                default => null,
            };
            if ($days !== null) {
                $query->where('created_at', '>=', now()->subDays($days));
            }
        }

        return $query->limit($limit)->get()->map(fn (CampaignBonus $b) => [
            'id' => $b->id,
            'uuid' => $b->uuid,
            'title' => $b->title,
            'bonus_type' => $b->bonus_type,
            'campaign_id' => $b->campaign_id,
            'campaign_name' => $b->campaign?->name,
            'meta' => is_array($b->meta) ? $b->meta : [],
            'content' => $b->content,
            'created_at' => $b->created_at?->toIso8601String(),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function viewerPayload(Campaign $campaign, CampaignBonus $bonus, string $username): array
    {
        $meta = is_array($bonus->meta) ? $bonus->meta : [];
        $type = (string) $bonus->bonus_type;

        $slides = $type === 'mini_course'
            ? $this->courseSlides($bonus, $meta)
            : $this->ebookSlides($bonus, $meta);

        return [
            'uuid' => $bonus->uuid,
            'title' => $bonus->title,
            'subtitle' => (string) ($meta['subtitle'] ?? ''),
            'bonus_type' => $type,
            'slides' => $slides,
            'download_url' => $type === 'ebook'
                ? route('public.campaign.bonus.download', ['username' => $username, 'slug' => $campaign->slug, 'bonusUuid' => $bonus->uuid])
                : null,
            'stack_url' => route('public.campaign.page', ['username' => $username, 'slug' => $campaign->slug, 'page' => 'bonus']),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    protected function ebookSlides(CampaignBonus $bonus, array $meta): array
    {
        $pages = is_array($meta['pages'] ?? null) ? $meta['pages'] : [];
        $subtitle = (string) ($meta['subtitle'] ?? '');

        $slides = [[
            'kind' => 'cover',
            'title' => $bonus->title,
            'subtitle' => $subtitle,
            'body_html' => '',
            'cover_gradient' => $meta['cover_gradient'] ?? $this->defaultGradient('ebook'),
        ]];

        if ($subtitle !== '') {
            $slides[] = [
                'kind' => 'intro',
                'title' => 'Introduction',
                'subtitle' => '',
                'body_html' => '<p class="viewer-lead">'.e($subtitle).'</p>',
            ];
        }

        if ($pages !== []) {
            $outlineItems = collect($pages)->map(fn ($p, $i) => '<li><span>'.($i + 1).'</span> '.e($p['title'] ?? 'Chapter '.($i + 1)).'</li>')->join('');
            $slides[] = [
                'kind' => 'outline',
                'title' => 'Book outline',
                'subtitle' => count($pages).' chapters',
                'body_html' => '<ul class="viewer-outline">'.$outlineItems.'</ul>',
            ];
        }

        foreach ($pages as $p) {
            $slides[] = [
                'kind' => 'content',
                'title' => (string) ($p['title'] ?? 'Chapter'),
                'subtitle' => 'Chapter '.(int) ($p['page'] ?? 0),
                'body_html' => (string) ($p['body_html'] ?? ''),
            ];
        }

        return $slides;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    protected function courseSlides(CampaignBonus $bonus, array $meta): array
    {
        $lessons = is_array($meta['slides'] ?? null) ? $meta['slides'] : [];
        $subtitle = (string) ($meta['subtitle'] ?? '');

        $slides = [[
            'kind' => 'cover',
            'title' => $bonus->title,
            'subtitle' => $subtitle,
            'body_html' => '<p class="viewer-lead">Swipe or use the arrows below to explore this mini course.</p>',
            'cover_gradient' => $meta['cover_gradient'] ?? $this->defaultGradient('mini_course'),
        ]];

        if ($lessons !== []) {
            $outlineItems = collect($lessons)->map(fn ($s, $i) => '<li><span>'.($i + 1).'</span> '.e($s['title'] ?? 'Lesson '.($i + 1)).'</li>')->join('');
            $slides[] = [
                'kind' => 'outline',
                'title' => 'Course outline',
                'subtitle' => count($lessons).' modules',
                'body_html' => '<ul class="viewer-outline">'.$outlineItems.'</ul>',
            ];
        }

        foreach ($lessons as $s) {
            $body = (string) ($s['body_html'] ?? '');
            if (! empty($s['takeaway'])) {
                $body .= '<div class="viewer-takeaway"><strong>Takeaway:</strong> '.e($s['takeaway']).'</div>';
            }
            $slides[] = [
                'kind' => 'lesson',
                'title' => (string) ($s['title'] ?? 'Lesson'),
                'subtitle' => (string) ($s['subtitle'] ?? ''),
                'body_html' => $body,
            ];
        }

        return $slides;
    }

    /**
     * @return array<string, mixed>
     */
    public function libraryIndexItem(CampaignBonus $bonus, string $username): array
    {
        $meta = is_array($bonus->meta) ? $bonus->meta : [];
        $type = (string) $bonus->bonus_type;

        $viewerUrl = $meta['viewer_url'] ?? null;
        if ((! is_string($viewerUrl) || $viewerUrl === '') && $bonus->relationLoaded('campaign') && $bonus->campaign) {
            $viewerUrl = route('public.campaign.bonus.viewer', [
                'username' => $username,
                'slug' => $bonus->campaign->slug,
                'bonusUuid' => $bonus->uuid,
            ]);
        }

        return [
            'id' => $bonus->id,
            'uuid' => $bonus->uuid,
            'title' => $bonus->title,
            'bonus_type' => $type,
            'status' => $bonus->status,
            'meta' => $meta,
            'content' => Str::limit(strip_tags((string) ($bonus->content ?? '')), 220),
            'campaign' => $bonus->campaign ? [
                'id' => $bonus->campaign->id,
                'name' => $bonus->campaign->name,
                'slug' => $bonus->campaign->slug,
            ] : null,
            'created_at' => $bonus->created_at?->toIso8601String(),
            'viewer_url' => is_string($viewerUrl) ? $viewerUrl : null,
            'value_label' => (string) ($meta['value_label'] ?? $this->defaultValueLabel($type)),
            'cover_gradient' => (string) ($meta['cover_gradient'] ?? $this->defaultGradient($type)),
            'page_count' => count($meta['pages'] ?? []),
            'slide_count' => count($meta['slides'] ?? []),
            'subtitle' => (string) ($meta['subtitle'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    protected function featuresFor(CampaignBonus $bonus, array $meta): array
    {
        if (is_array($meta['features'] ?? null) && $meta['features'] !== []) {
            return array_values(array_map('strval', $meta['features']));
        }

        $type = (string) $bonus->bonus_type;
        if ($type === 'ebook') {
            $count = count($meta['pages'] ?? []);

            return array_filter([
                $count > 0 ? "{$count}-chapter professional PDF guide" : null,
                'Instant download — yours to keep',
                'Actionable tactics specific to this offer',
            ]);
        }

        $count = count($meta['slides'] ?? []);

        return array_filter([
            $count > 0 ? "{$count} swipeable lessons" : null,
            'Step-by-step mini course format',
            'Works on mobile and desktop',
        ]);
    }

    protected function typeLabel(string $type): string
    {
        return match ($type) {
            'mini_course' => 'MINI COURSE',
            'mini_app' => 'MINI APP',
            default => 'PDF GUIDE',
        };
    }

    protected function ctaLabel(string $type): string
    {
        return match ($type) {
            'mini_app' => 'Get access ↗',
            default => 'Get instant access ↗',
        };
    }

    protected function defaultValueLabel(string $type): string
    {
        return match ($type) {
            'mini_course' => '$97 value',
            'mini_app' => '$47 value',
            default => '$27 value',
        };
    }

    protected function defaultGradient(string $type): string
    {
        return match ($type) {
            'mini_course' => 'linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%)',
            'mini_app' => 'linear-gradient(135deg,#0ea5e9 0%,#06b6d4 100%)',
            default => 'linear-gradient(135deg,#dc2626 0%,#991b1b 100%)',
        };
    }
}
