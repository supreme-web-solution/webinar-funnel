<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignBonus;

class CampaignGenerationStateService
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function mark(Campaign $campaign, string $step, array $extra = []): void
    {
        $state = $campaign->generation_state ?? [];
        $state[$step] = array_merge([
            'generated' => true,
            'at' => now()->toIso8601String(),
        ], $extra);

        $campaign->update(['generation_state' => $state]);
        $campaign->refresh();
    }

    public function clear(Campaign $campaign, string $step): void
    {
        $state = $campaign->generation_state ?? [];
        unset($state[$step]);
        $campaign->update(['generation_state' => $state]);
        $campaign->refresh();
    }

    /**
     * @param  list<string>  $steps
     */
    public function clearMany(Campaign $campaign, array $steps): void
    {
        $state = $campaign->generation_state ?? [];
        foreach ($steps as $step) {
            unset($state[$step]);
        }
        $campaign->update(['generation_state' => $state]);
        $campaign->refresh();
    }

    public function isGenerated(Campaign $campaign, string $step): bool
    {
        $entry = ($campaign->generation_state ?? [])[$step] ?? null;

        return is_array($entry) && ($entry['generated'] ?? false) === true;
    }

    /**
     * Infer flags from saved content when migrating older campaigns.
     */
    public function syncFromContent(Campaign $campaign): void
    {
        $campaign->loadMissing(['pages', 'bonuses', 'emails', 'funnels']);
        $state = $campaign->generation_state ?? [];
        $changed = false;

        if (($campaign->knowledge['status'] ?? '') === 'ready' && ! ($state['knowledge']['generated'] ?? false)) {
            $state['knowledge'] = ['generated' => true, 'at' => now()->toIso8601String()];
            $changed = true;
        }

        $leadMagnet = $campaign->pages->firstWhere('page_type', 'lead_magnet');
        $lmContent = is_array($leadMagnet?->content) ? $leadMagnet->content : [];

        $suggestions = $lmContent['suggestions'] ?? [];
        if (is_array($suggestions) && count($suggestions) > 0 && ! ($state['lead_magnet_suggest']['generated'] ?? false)) {
            $state['lead_magnet_suggest'] = ['generated' => true, 'at' => now()->toIso8601String()];
            $changed = true;
        }

        if ($this->leadMagnetContentReady($lmContent) && ! ($state['lead_magnet']['generated'] ?? false)) {
            $state['lead_magnet'] = [
                'generated' => true,
                'at' => now()->toIso8601String(),
                'selected_id' => $lmContent['selected_id'] ?? null,
            ];
            $changed = true;
        }

        if ($this->funnelPagesReady($campaign) && ! ($state['pages']['generated'] ?? false)) {
            $state['pages'] = ['generated' => true, 'at' => now()->toIso8601String()];
            $changed = true;
        }

        $bonus = $campaign->bonuses->first();
        if ($bonus instanceof CampaignBonus && $this->bonusContentReady($bonus) && ! ($state['bonuses']['generated'] ?? false)) {
            $state['bonuses'] = [
                'generated' => true,
                'at' => now()->toIso8601String(),
                'selected_id' => $bonus->meta['selected_id'] ?? null,
            ];
            $changed = true;
        }

        $bonusType = $campaign->meta['bonus_type'] ?? null;
        $bonusSuggestions = $campaign->meta['bonus_suggestions'] ?? [];
        if ($bonusType && is_array($bonusSuggestions) && count($bonusSuggestions) >= 3 && ! ($state['bonuses_suggest']['generated'] ?? false)) {
            $state['bonuses_suggest'] = [
                'generated' => true,
                'at' => now()->toIso8601String(),
                'bonus_type' => $bonusType,
            ];
            $changed = true;
        }

        if ($campaign->emails->isNotEmpty() && ! ($state['emails']['generated'] ?? false)) {
            $state['emails'] = ['generated' => true, 'at' => now()->toIso8601String()];
            $changed = true;
        }

        if ($campaign->type === Campaign::TYPE_WEBINAR
            && $campaign->funnels->isNotEmpty()
            && ! ($state['webinar']['generated'] ?? false)) {
            $state['webinar'] = ['generated' => true, 'at' => now()->toIso8601String()];
            $changed = true;
        }

        if ($changed) {
            $campaign->update(['generation_state' => $state]);
            $campaign->refresh();
        }
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function leadMagnetContentReady(array $content): bool
    {
        if (($content['generated'] ?? false) === true) {
            return true;
        }

        if (($content['status'] ?? '') === 'ready') {
            return true;
        }

        return ! empty($content['pages'])
            && is_string($content['download_path'] ?? null)
            && $content['download_path'] !== '';
    }

    public function funnelPagesReady(Campaign $campaign): bool
    {
        foreach (['squeeze', 'thankyou'] as $type) {
            $page = $campaign->pages->firstWhere('page_type', $type);
            $content = is_array($page?->content) ? $page->content : [];
            if (empty($content['headline']) && empty($content['title']) && empty($content['subheadline'])) {
                return false;
            }
        }

        return true;
    }

    public function bonusContentReady(CampaignBonus $bonus): bool
    {
        $meta = is_array($bonus->meta) ? $bonus->meta : [];

        return (is_array($meta['pages'] ?? null) && count($meta['pages']) > 0)
            || (is_array($meta['slides'] ?? null) && count($meta['slides']) > 0);
    }
}
