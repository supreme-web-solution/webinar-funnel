<?php

namespace App\Ai\Tools;

use App\Models\Campaign;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class GetCampaignTool extends GatedTool
{
    public function toolName(): string
    {
        return 'get_campaign';
    }

    public function permission(): string
    {
        return 'read';
    }

    public function description(): string
    {
        return 'Get one campaign by numeric id including counts of pages, bonuses, emails, and funnels.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->integer()->required(),
        ];
    }

    protected function run(Request $request): string
    {
        $campaign = Campaign::query()
            ->where('user_id', $this->user->id)
            ->where('id', (int) $request['campaign_id'])
            ->withCount(['pages', 'bonuses', 'emails', 'funnels', 'trackedLinks'])
            ->first();

        if ($campaign === null) {
            return $this->json(['error' => 'Campaign not found.']);
        }

        return $this->json([
            'id' => $campaign->id,
            'name' => $campaign->name,
            'type' => $campaign->type,
            'status' => $campaign->status,
            'slug' => $campaign->slug,
            'offer_url' => $campaign->offer_url,
            'affiliate_link' => $campaign->affiliate_link,
            'wizard_step' => $campaign->wizard_step,
            'pages_count' => $campaign->pages_count,
            'bonuses_count' => $campaign->bonuses_count,
            'emails_count' => $campaign->emails_count,
            'funnels_count' => $campaign->funnels_count,
            'edit_url' => '/campaigns/'.$campaign->id.'/edit',
        ]);
    }
}
