<?php

namespace App\Services\AiEmployee;

use App\Jobs\Campaigns\RunCampaignQuickStartJob;
use App\Models\Campaign;
use App\Models\ContentEmployeePlan;
use App\Models\TrackedLink;
use App\Models\User;
use App\Services\Campaigns\CampaignAutoresponderService;
use App\Services\Campaigns\CampaignBuilderService;
use App\Services\Campaigns\CampaignGenerationProgressService;
use App\Services\Campaigns\CampaignLeadCaptureService;
use App\Services\Campaigns\CampaignTrafficHubService;
use App\Services\Campaigns\TrackedLinkService;
use Illuminate\Support\Str;

class CommandCenterDomainService
{
    public function __construct(
        protected CampaignTrafficHubService $trafficHub,
        protected CampaignGenerationProgressService $generationProgress,
        protected CampaignLeadCaptureService $leadCapture,
        protected TrackedLinkService $trackedLinks,
    ) {}

    public function createCampaign(
        User $user,
        string $name,
        string $type = 'sales',
        ?string $offerUrl = null,
        ?string $affiliateLink = null,
        ?string $marketplace = null,
    ): Campaign {
        $type = in_array($type, ['sales', 'webinar'], true) ? $type : 'sales';
        $slug = $this->uniqueCampaignSlug($user->id, Str::slug($name) ?: 'campaign');

        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'status' => 'draft',
            'wizard_step' => 1,
            'offer_url' => $offerUrl,
            'affiliate_link' => $affiliateLink,
            'marketplace' => $marketplace,
        ]);

        $this->trafficHub->ensureTrafficFunnel($campaign);

        return $campaign;
    }

    public function quickStartCampaign(
        User $user,
        string $offerUrl,
        ?string $name = null,
        string $type = 'sales',
        ?string $affiliateLink = null,
        ?string $marketplace = null,
        ?string $keyword = null,
    ): Campaign {
        $title = trim((string) ($name ?: $keyword ?: 'New Campaign'));
        $slug = $this->uniqueCampaignSlug($user->id, Str::slug($title) ?: 'campaign');

        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => $title,
            'slug' => $slug,
            'type' => in_array($type, ['sales', 'webinar'], true) ? $type : 'sales',
            'status' => 'draft',
            'wizard_step' => 1,
            'offer_url' => $offerUrl,
            'affiliate_link' => $affiliateLink,
            'marketplace' => $marketplace,
            'offer_data' => [
                'product_name' => $title,
                'source' => 'command_center',
                'marketplace' => $marketplace,
                'keyword' => $keyword,
            ],
            'meta' => ['quick_start' => true, 'source' => 'command_center'],
        ]);

        $this->trafficHub->ensureTrafficFunnel($campaign);

        if (! $this->generationProgress->isRunning($campaign)) {
            $this->generationProgress->start($campaign, 'quick_start', 'Building full campaign from offer…');
            RunCampaignQuickStartJob::dispatch($campaign->id);
        }

        return $campaign;
    }

    public function publishCampaign(User $user, int $campaignId): Campaign
    {
        $campaign = $this->ownedCampaign($user, $campaignId);
        $this->trafficHub->ensureTrafficFunnel($campaign);

        $campaign->update([
            'status' => 'published',
            'published_at' => now(),
            'wizard_step' => 8,
        ]);

        if ($campaign->type === Campaign::TYPE_WEBINAR) {
            $builder = app(CampaignBuilderService::class);
            $offer = $campaign->offer_data ?? ['product_name' => $campaign->name];
            if ($builder->primaryOptinFunnel($campaign) === null || $builder->primaryWebinarFunnel($campaign) === null) {
                $builder->buildWebinarFunnels($campaign->fresh(), $offer);
            }
            $campaign->funnels()->update(['status' => 'published', 'published_at' => now()]);
        } else {
            $this->leadCapture->ensureOptinFunnel($campaign->fresh());
        }

        app(CampaignAutoresponderService::class)->syncToLeadFunnel($campaign->fresh());

        return $campaign->fresh();
    }

    public function pauseCampaign(User $user, int $campaignId): Campaign
    {
        $campaign = $this->ownedCampaign($user, $campaignId);
        $campaign->funnels()->update(['status' => 'draft', 'published_at' => null]);
        $campaign->update([
            'status' => 'draft',
            'meta' => array_merge($campaign->meta ?? [], ['paused_at' => now()->toIso8601String()]),
        ]);

        return $campaign->fresh();
    }

    /**
     * @return array{deleted: true, id: int, name: string}
     */
    public function deleteCampaign(User $user, int $campaignId): array
    {
        $campaign = $this->ownedCampaign($user, $campaignId);
        $payload = [
            'deleted' => true,
            'id' => $campaign->id,
            'name' => $campaign->name,
        ];
        $campaign->delete();

        return $payload;
    }

    /**
     * @return array{deleted: true, id: int, code: string, label: ?string}
     */
    public function deleteTrackedLink(User $user, int $linkId): array
    {
        $link = TrackedLink::query()
            ->where('user_id', $user->id)
            ->where('id', $linkId)
            ->first();

        if ($link === null) {
            throw new \InvalidArgumentException('Tracked link #'.$linkId.' was not found.');
        }

        $payload = [
            'deleted' => true,
            'id' => $link->id,
            'code' => $link->code,
            'label' => $link->label,
        ];
        $link->delete();

        return $payload;
    }

    /**
     * @return array{deleted: true, id: int, status: string}
     */
    public function deleteContentPlan(User $user, int $planId): array
    {
        $plan = ContentEmployeePlan::query()
            ->where('user_id', $user->id)
            ->where('id', $planId)
            ->first();

        if ($plan === null) {
            throw new \InvalidArgumentException('Content plan #'.$planId.' was not found.');
        }

        if ($plan->status === ContentEmployeePlan::STATUS_EXECUTING) {
            throw new \InvalidArgumentException('Cannot delete a plan that is currently executing.');
        }

        $payload = [
            'deleted' => true,
            'id' => $plan->id,
            'status' => $plan->status,
        ];
        $plan->items()->delete();
        $plan->delete();

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $geoRules
     * @param  array<string, mixed>|null  $deviceRules
     * @return array<string, mixed>
     */
    public function createTrackedLink(
        User $user,
        string $destinationUrl,
        ?string $label = null,
        ?int $campaignId = null,
        ?array $geoRules = null,
        ?array $deviceRules = null,
    ): array {
        if ($campaignId !== null) {
            $this->ownedCampaign($user, $campaignId);
        }

        $link = $this->trackedLinks->createForUser(
            $user->id,
            $destinationUrl,
            $label,
            $geoRules,
            $deviceRules,
            $campaignId,
        );

        return [
            'id' => $link->id,
            'code' => $link->code,
            'label' => $link->label,
            'destination_url' => $link->destination_url,
            'public_url' => $link->publicUrl(),
        ];
    }

    public function ownedCampaign(User $user, int $campaignId): Campaign
    {
        $campaign = Campaign::query()
            ->where('user_id', $user->id)
            ->where('id', $campaignId)
            ->first();

        if ($campaign === null) {
            throw new \InvalidArgumentException('Campaign #'.$campaignId.' was not found.');
        }

        return $campaign;
    }

    protected function uniqueCampaignSlug(int $userId, string $base): string
    {
        $slug = $base;
        $i = 1;
        while (Campaign::query()->where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
