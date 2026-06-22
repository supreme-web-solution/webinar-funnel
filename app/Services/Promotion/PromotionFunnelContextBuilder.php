<?php

namespace App\Services\Promotion;

use App\Models\Funnel;
use App\Models\TemplateVersion;
use Illuminate\Support\Str;

final class PromotionFunnelContextBuilder
{
    private const GENERIC_WEBINAR_TITLES = [
        'watch this training completely to be our next success story',
    ];

    /**
     * @return array{
     *   product_name: string,
     *   category: string|null,
     *   conversion_style: string|null,
     *   optin_intro: string|null,
     *   webinar_description: string|null,
     *   bullet_points: list<string>,
     *   template_keywords: list<string>,
     *   traffic_keywords: list<string>,
     *   audience: string|null,
     *   cta_label: string|null,
     * }
     */
    public function build(Funnel $funnel): array
    {
        $funnel->loadMissing(['settings', 'template.versions', 'keywords']);

        $settings = $funnel->settings;
        $template = $funnel->template;
        $version = $this->currentTemplateVersion($template?->versions);

        $optinSchema = is_array($version?->optin_schema) ? $version->optin_schema : [];
        $webinarSchema = is_array($version?->webinar_schema) ? $version->webinar_schema : [];

        $hero = is_array($optinSchema['hero'] ?? null) ? $optinSchema['hero'] : [];
        $optinIntro = $this->firstNonEmptyString([
            $hero['subheadline'] ?? null,
            $this->extractIntroFromDescription($settings?->webinar_description),
            $webinarSchema['description'] ?? null,
        ]);

        $bullets = $this->normalizeBulletList($optinSchema['what_youll_discover'] ?? []);

        if ($bullets === [] && is_string($settings?->webinar_description) && $settings->webinar_description !== '') {
            $bullets = $this->extractBulletsFromDescription($settings->webinar_description);
        }

        $productName = $this->resolveProductName($funnel, $template?->name, $settings?->offers ?? []);

        return [
            'product_name' => $productName,
            'category' => $template?->category,
            'conversion_style' => $template?->conversion_style,
            'optin_intro' => $optinIntro,
            'webinar_description' => $this->cleanText($settings?->webinar_description)
                ?: $this->cleanText($webinarSchema['description'] ?? null),
            'bullet_points' => $bullets,
            'template_keywords' => array_values(array_filter(
                is_array($template?->suggested_keywords) ? $template->suggested_keywords : [],
                fn ($keyword): bool => is_string($keyword) && trim($keyword) !== ''
            )),
            'traffic_keywords' => $funnel->keywords()->pluck('name')->filter()->values()->all(),
            'audience' => $this->inferAudience($template?->category, $optinIntro),
            'cta_label' => $this->cleanText($settings?->webinar_cta_label),
        ];
    }

    /**
     * @param  list<mixed>  $offers
     */
    public function resolveProductName(Funnel $funnel, ?string $templateName, array $offers): string
    {
        $candidates = [];

        if (is_string($templateName) && trim($templateName) !== '') {
            $candidates[] = $this->cleanOfferName($templateName);
        }

        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $name = $this->cleanText($offer['name'] ?? $offer['title'] ?? null);
            if ($name !== null) {
                $candidates[] = $this->cleanOfferName($name);
            }
        }

        $funnelName = $this->cleanText($funnel->name);
        if ($funnelName !== null && ! $this->isGenericWebinarTitle($funnelName)) {
            $candidates[] = $this->cleanOfferName($funnelName);
        }

        $webinarTitle = $this->cleanText($funnel->settings?->webinar_title);
        if ($webinarTitle !== null && ! $this->isGenericWebinarTitle($webinarTitle)) {
            $candidates[] = $webinarTitle;
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return 'this offer';
    }

    public function isGenericWebinarTitle(?string $title): bool
    {
        $normalized = mb_strtolower(trim((string) $title));

        if ($normalized === '') {
            return true;
        }

        if (in_array($normalized, self::GENERIC_WEBINAR_TITLES, true)) {
            return true;
        }

        return str_contains($normalized, 'watch this training completely');
    }

    /**
     * @param  iterable<int, TemplateVersion>|null  $versions
     */
    private function currentTemplateVersion($versions): ?TemplateVersion
    {
        if ($versions === null) {
            return null;
        }

        foreach ($versions as $version) {
            if ($version->is_current) {
                return $version;
            }
        }

        $latest = null;
        foreach ($versions as $version) {
            if ($latest === null || $version->version > $latest->version) {
                $latest = $version;
            }
        }

        return $latest;
    }

    /**
     * @param  list<mixed>  $values
     */
    private function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            $text = $this->cleanText(is_string($value) ? $value : null);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    private function cleanText(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $text !== '' ? $text : null;
    }

    private function cleanOfferName(string $name): string
    {
        $name = trim(preg_replace('/\s+Offer$/i', '', trim($name)) ?? '');

        return $name;
    }

    /**
     * @return list<string>
     */
    private function normalizeBulletList(mixed $bullets): array
    {
        if (! is_array($bullets)) {
            return [];
        }

        $normalized = [];
        foreach ($bullets as $bullet) {
            $text = $this->cleanText(is_string($bullet) ? $bullet : null);
            if ($text !== null) {
                $normalized[] = $text;
            }
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function extractBulletsFromDescription(string $description): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $description) ?: [];
        $bullets = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '- ')) {
                $bullets[] = trim(substr($line, 2));
            }
        }

        return $bullets;
    }

    private function extractIntroFromDescription(?string $description): ?string
    {
        if (! is_string($description) || trim($description) === '') {
            return null;
        }

        $parts = preg_split("/\n\s*\n/", trim($description), 3) ?: [];

        return $this->cleanText($parts[1] ?? $parts[0] ?? null);
    }

    private function inferAudience(?string $category, ?string $optinIntro): ?string
    {
        if (is_string($category) && $category !== '') {
            return match ($category) {
                'marketing' => 'marketers, affiliates, and online business owners',
                'coaching' => 'coaches and consultants',
                'real-estate' => 'real estate professionals and investors',
                'crypto' => 'crypto and Web3 enthusiasts',
                'ecommerce' => 'ecommerce sellers and brand owners',
                'health' => 'health and wellness audiences',
                'finance' => 'people interested in money, investing, and financial freedom',
                'education' => 'students, creators, and educators',
                'consulting' => 'consultants and service providers',
                default => 'beginners building an online business',
            };
        }

        if (is_string($optinIntro) && str_contains(strtolower($optinIntro), 'beginner')) {
            return 'beginners and side-hustlers';
        }

        return null;
    }
}
