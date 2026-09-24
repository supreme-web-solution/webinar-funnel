<?php

namespace App\Services\Traffic;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\User;
use App\Services\Campaigns\CampaignBuilderService;
use Illuminate\Support\Str;

final class StandaloneTrafficWorkspaceService
{
    public function __construct(
        private readonly CampaignBuilderService $campaignBuilder,
    ) {}

    public function funnelForUser(User $user): Funnel
    {
        $existing = Funnel::query()
            ->where('user_id', $user->id)
            ->whereNull('campaign_id')
            ->where('meta->campaign_variant', 'standalone_traffic')
            ->first();

        if ($existing) {
            return $existing;
        }

        $template = $this->campaignBuilder->ensureBlankTemplate();
        $slugBase = 'traffic-'.Str::slug($user->username ?? ('user-'.$user->id));
        $slug = $this->uniqueFunnelSlug((int) $user->id, $slugBase);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'campaign_id' => null,
            'template_id' => $template->id,
            'name' => 'Traffic Workspace',
            'slug' => $slug,
            'status' => 'draft',
            'meta' => [
                'campaign_variant' => 'standalone_traffic',
                'template_version' => 1,
            ],
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'headline' => 'Standalone traffic workspace',
            'subheadline' => 'Create and schedule social content without a campaign.',
            'traffic_ai_reply_enabled' => false,
        ]);

        return $funnel->fresh(['settings']);
    }

    public function isStandaloneFunnel(Funnel $funnel): bool
    {
        return $funnel->campaign_id === null
            && (($funnel->meta['campaign_variant'] ?? null) === 'standalone_traffic');
    }

    /**
     * Same shape as CampaignTrafficHubService::hubPayload() for shared UI components.
     *
     * @return array<string, mixed>
     */
    public function hubPayload(User $user): array
    {
        $funnel = $this->funnelForUser($user);

        return [
            'standalone' => true,
            'campaign' => null,
            'workspace' => [
                'name' => 'Standalone traffic workspace',
                'status' => 'active',
            ],
            'traffic_funnel' => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'slug' => $funnel->slug,
                'status' => $funnel->status,
            ],
            'routes' => [
                'hub' => route('traffic.workspace.index'),
                'free' => route('traffic.workspace.free'),
                'promotion_posts' => route('traffic.workspace.promotion.posts'),
                'promotion_calendar' => route('traffic.workspace.promotion.calendar'),
                'ads' => route('traffic.workspace.ads'),
                'traffic_index' => route('traffic.index'),
                'setup' => route('traffic.index').'?tab=setup',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function hubPayloadForFunnel(Funnel $funnel): ?array
    {
        if (! $this->isStandaloneFunnel($funnel)) {
            return null;
        }

        $funnel->loadMissing('user');

        return $this->hubPayload($funnel->user);
    }

    /**
     * @return array<string, mixed>
     */
    public function workspacePayload(User $user): array
    {
        return $this->hubPayload($user);
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
