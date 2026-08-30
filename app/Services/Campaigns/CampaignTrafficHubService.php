<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\Funnel;
use App\Models\FunnelSetting;
use Illuminate\Support\Str;

class CampaignTrafficHubService
{
    public function __construct(
        protected CampaignBuilderService $campaignBuilder,
    ) {}

    public function primaryTrafficFunnel(Campaign $campaign): ?Funnel
    {
        $meta = $campaign->meta ?? [];
        $primaryId = $meta['primary_traffic_funnel_id'] ?? null;

        if ($primaryId) {
            $primary = $campaign->funnels()->find($primaryId);
            if ($primary) {
                return $primary;
            }
        }

        return $campaign->funnels()
            ->where('meta->campaign_variant', 'traffic')
            ->first();
    }

    public function ensureTrafficFunnel(Campaign $campaign): Funnel
    {
        $existing = $this->primaryTrafficFunnel($campaign);
        if ($existing) {
            return $existing;
        }

        $template = $this->campaignBuilder->ensureBlankTemplate();
        $slugBase = Str::slug($campaign->slug.'-traffic');
        $slug = $this->uniqueFunnelSlug((int) $campaign->user_id, $slugBase);

        $product = (string) ($campaign->offer_data['product_name'] ?? $campaign->name);
        $suggested = $this->suggestedKeywordsFromCampaign($campaign);

        $funnel = Funnel::query()->create([
            'user_id' => $campaign->user_id,
            'campaign_id' => $campaign->id,
            'template_id' => $template->id,
            'name' => $campaign->name.' — Traffic Hub',
            'slug' => $slug,
            'status' => 'draft',
            'meta' => [
                'campaign_variant' => 'traffic',
                'template_version' => 1,
                'suggested_keywords' => $suggested,
            ],
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'headline' => "Traffic for {$product}",
            'subheadline' => 'Discover mentions, publish content, and run ads for this campaign.',
            'traffic_ai_reply_enabled' => false,
        ]);

        $meta = $campaign->meta ?? [];
        $meta['primary_traffic_funnel_id'] = $funnel->id;
        $campaign->update(['meta' => $meta]);

        return $funnel->fresh(['settings']);
    }

    /**
     * @return array<string, mixed>
     */
    public function hubPayload(Campaign $campaign): array
    {
        $funnel = $this->ensureTrafficFunnel($campaign);

        return [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'type' => $campaign->type,
                'status' => $campaign->status,
            ],
            'traffic_funnel' => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'slug' => $funnel->slug,
                'status' => $funnel->status,
            ],
            'routes' => [
                'hub' => route('campaigns.traffic.index', $campaign),
                'free' => route('campaigns.traffic.free', $campaign),
                'promotion_posts' => route('campaigns.traffic.promotion.posts', $campaign),
                'promotion_calendar' => route('campaigns.traffic.promotion.calendar', $campaign),
                'ads' => route('campaigns.traffic.ads', $campaign),
                'campaign_edit' => route('campaigns.edit', $campaign),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    protected function suggestedKeywordsFromCampaign(Campaign $campaign): array
    {
        return app(CampaignKnowledgeContextService::class)->trafficKeywordSuggestions($campaign);
    }

    protected function uniqueFunnelSlug(int $userId, string $base): string
    {
        $slug = $base;
        $i = 0;
        while (Funnel::query()->where('user_id', $userId)->where('slug', $slug)->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
