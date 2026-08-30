<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\Funnel;

class CampaignKnowledgeContextService
{
    public function hasKnowledge(Campaign $campaign): bool
    {
        $knowledge = is_array($campaign->knowledge) ? $campaign->knowledge : [];

        if (($knowledge['status'] ?? null) === 'ready') {
            return true;
        }

        return ! empty($knowledge['pass1']) && is_array($knowledge['pass1']);
    }

    /**
     * Short phrases suitable for Reddit/YouTube/X/news mention monitoring.
     *
     * @return list<string>
     */
    public function trafficKeywordSuggestions(Campaign $campaign): array
    {
        if (! $this->hasKnowledge($campaign)) {
            return $this->fallbackTrafficKeywords($campaign);
        }

        $ctx = app(CampaignKnowledgeBuilderService::class)->contextForGeneration($campaign);
        $offer = is_array($ctx['offer']) ? $ctx['offer'] : [];
        $pass1 = is_array($ctx['pass1']) ? $ctx['pass1'] : [];
        $pass2 = is_array($ctx['pass2']) ? $ctx['pass2'] : [];

        $candidates = [];

        foreach ([
            $offer['product_name'] ?? null,
            $offer['niche'] ?? null,
            $pass1['unique_mechanism'] ?? null,
            ...($pass1['target_audience'] ?? []),
            ...($pass1['competitor_alternatives'] ?? []),
            ...($pass1['core_pain_points'] ?? []),
        ] as $item) {
            $this->pushKeywordCandidate($candidates, $item);
        }

        foreach ($this->bulletsFromPass2($pass2) as $bullet) {
            $this->pushKeywordCandidate($candidates, $this->shortenForKeyword($bullet));
        }

        foreach ($pass1['hook_angles'] ?? [] as $hook) {
            $this->pushKeywordCandidate($candidates, $this->shortenForKeyword($hook));
        }

        return array_values(array_slice(array_unique($candidates), 0, 8));
    }

    /**
     * Post topic seeds from campaign knowledge (hooks, bullets, email angles, objections).
     *
     * @return list<string>
     */
    public function promotionTopicSeeds(Campaign $campaign): array
    {
        if (! $this->hasKnowledge($campaign)) {
            return [];
        }

        $ctx = app(CampaignKnowledgeBuilderService::class)->contextForGeneration($campaign);
        $pass1 = is_array($ctx['pass1']) ? $ctx['pass1'] : [];
        $pass2 = is_array($ctx['pass2']) ? $ctx['pass2'] : [];

        $seeds = [];

        foreach ($pass1['hook_angles'] ?? [] as $hook) {
            $this->pushTextSeed($seeds, $hook);
        }

        foreach ($pass1['core_pain_points'] ?? [] as $pain) {
            $this->pushTextSeed($seeds, is_string($pain) ? $pain : null);
        }

        foreach ($pass1['desired_outcomes'] ?? [] as $outcome) {
            $this->pushTextSeed($seeds, is_string($outcome) ? $outcome : null);
        }

        foreach ($pass1['objections'] ?? [] as $objection) {
            if (is_array($objection)) {
                $this->pushTextSeed($seeds, $objection['objection'] ?? null);
            } else {
                $this->pushTextSeed($seeds, $objection);
            }
        }

        foreach ($this->bulletsFromPass2($pass2) as $bullet) {
            $this->pushTextSeed($seeds, $bullet);
        }

        foreach ($pass2['email_sequence_plan'] ?? [] as $email) {
            if (! is_array($email)) {
                continue;
            }
            $this->pushTextSeed($seeds, $email['angle'] ?? null);
            $this->pushTextSeed($seeds, $email['subject_hint'] ?? null);
        }

        foreach ($pass2['lead_magnet_ideas'] ?? [] as $idea) {
            if (is_array($idea)) {
                $this->pushTextSeed($seeds, $idea['title'] ?? null);
            }
        }

        foreach ($pass2['bonus_concepts'] ?? [] as $bonus) {
            if (is_array($bonus)) {
                $this->pushTextSeed($seeds, $bonus['title'] ?? null);
                $this->pushTextSeed($seeds, $bonus['promise'] ?? null);
            }
        }

        return array_values(array_unique($seeds));
    }

    public function productName(Campaign $campaign): string
    {
        $offer = is_array($campaign->offer_data) ? $campaign->offer_data : [];
        $name = trim((string) ($offer['product_name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        $pass1 = is_array($campaign->knowledge['pass1'] ?? null) ? $campaign->knowledge['pass1'] : [];
        $summary = trim((string) ($pass1['product_summary'] ?? ''));

        if ($summary !== '') {
            return mb_strlen($summary) <= 80 ? $summary : (string) mb_substr($summary, 0, 77).'…';
        }

        return trim((string) $campaign->name) ?: 'this offer';
    }

    public function audienceLabel(Campaign $campaign): ?string
    {
        $pass1 = is_array($campaign->knowledge['pass1'] ?? null) ? $campaign->knowledge['pass1'] : [];
        $audience = $pass1['target_audience'] ?? [];

        if (! is_array($audience) || $audience === []) {
            return null;
        }

        $parts = array_values(array_filter(array_map(
            fn ($item) => is_string($item) ? trim($item) : '',
            $audience,
        )));

        return $parts === [] ? null : implode(', ', array_slice($parts, 0, 3));
    }

    public function offerSummary(Campaign $campaign): ?string
    {
        $pass1 = is_array($campaign->knowledge['pass1'] ?? null) ? $campaign->knowledge['pass1'] : [];
        $summary = trim((string) ($pass1['product_summary'] ?? ''));

        if ($summary !== '') {
            return $summary;
        }

        $pass2 = is_array($campaign->knowledge['pass2'] ?? null) ? $campaign->knowledge['pass2'] : [];
        $squeeze = is_array($pass2['squeeze_page_strategy'] ?? null) ? $pass2['squeeze_page_strategy'] : [];
        $webinar = is_array($pass2['webinar_registration_strategy'] ?? null) ? $pass2['webinar_registration_strategy'] : [];

        foreach ([$squeeze['subheadline'] ?? null, $webinar['subheadline'] ?? null, $webinar['description'] ?? null] as $text) {
            $clean = trim((string) $text);
            if ($clean !== '') {
                return $clean;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function promotionOverlayForFunnel(Funnel $funnel): ?array
    {
        $funnel->loadMissing('campaign');
        $campaign = $funnel->campaign;

        if (! $campaign instanceof Campaign || ! $this->hasKnowledge($campaign)) {
            return null;
        }

        $ctx = app(CampaignKnowledgeBuilderService::class)->contextForGeneration($campaign);
        $pass1 = is_array($ctx['pass1']) ? $ctx['pass1'] : [];

        return [
            'product_name' => $this->productName($campaign),
            'audience' => $this->audienceLabel($campaign),
            'optin_intro' => $this->offerSummary($campaign),
            'webinar_description' => trim((string) ($pass1['product_summary'] ?? '')) ?: null,
            'bullet_points' => $this->promotionTopicSeeds($campaign),
            'template_keywords' => $this->trafficKeywordSuggestions($campaign),
            'knowledge_pass1' => $pass1,
            'knowledge_pass2' => is_array($ctx['pass2']) ? $ctx['pass2'] : [],
        ];
    }

    /**
     * @return list<string>
     */
    protected function fallbackTrafficKeywords(Campaign $campaign): array
    {
        $offer = is_array($campaign->offer_data) ? $campaign->offer_data : [];
        $keywords = [];

        foreach ([$offer['product_name'] ?? null, $offer['niche'] ?? null, $campaign->name] as $item) {
            $this->pushKeywordCandidate($keywords, $item);
        }

        return array_values(array_slice(array_unique($keywords), 0, 8));
    }

    /**
     * @param  array<string, mixed>  $pass2
     * @return list<string>
     */
    protected function bulletsFromPass2(array $pass2): array
    {
        $bullets = [];

        foreach ([
            $pass2['squeeze_page_strategy'] ?? null,
            $pass2['webinar_registration_strategy'] ?? null,
        ] as $strategy) {
            if (! is_array($strategy)) {
                continue;
            }
            foreach ($strategy['bullets'] ?? [] as $bullet) {
                if (is_string($bullet) && trim($bullet) !== '') {
                    $bullets[] = trim($bullet);
                }
            }
        }

        return $bullets;
    }

    /**
     * @param  list<string>  $bucket
     */
    protected function pushKeywordCandidate(array &$bucket, mixed $value): void
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text) > 80) {
            return;
        }

        $bucket[] = $text;
    }

    /**
     * @param  list<string>  $bucket
     */
    protected function pushTextSeed(array &$bucket, mixed $value): void
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text) > 255) {
            return;
        }

        $bucket[] = $text;
    }

    protected function shortenForKeyword(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= 80) {
            return $text;
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        $short = '';
        foreach ($words as $word) {
            $next = $short === '' ? $word : $short.' '.$word;
            if (mb_strlen($next) > 80) {
                break;
            }
            $short = $next;
        }

        return $short !== '' ? $short : mb_substr($text, 0, 80);
    }
}
