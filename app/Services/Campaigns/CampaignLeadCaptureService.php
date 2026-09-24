<?php

namespace App\Services\Campaigns;

use App\Jobs\DispatchLeadToEspJob;
use App\Models\Campaign;
use App\Models\DispatchJobLog;
use App\Models\Funnel;
use App\Models\FunnelIntegration;
use App\Models\FunnelPage;
use App\Models\IntegrationAccount;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\TemplateVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CampaignLeadCaptureService
{
    public function __construct(
        protected CampaignBuilderService $campaignBuilder,
    ) {}

    /**
     * Mirror campaign squeeze opt-ins into the main leads table + ESP integrations.
     *
     * @param  array{name?: string|null, email: string}  $validated
     */
    public function capture(Campaign $campaign, array $validated, Request $request): ?Lead
    {
        $funnel = $this->resolveLeadFunnel($campaign);
        if (! $funnel) {
            Log::warning('[CampaignLeadCapture] No funnel available for ESP routing', [
                'campaign_id' => $campaign->id,
                'campaign_type' => $campaign->type,
            ]);

            return null;
        }

        $emailHash = hash('sha256', Str::lower(trim($validated['email'])));

        $lead = Lead::query()->firstOrCreate(
            [
                'funnel_id' => $funnel->id,
                'email_hash' => $emailHash,
            ],
            [
                'name' => (string) ($validated['name'] ?? ''),
                'email' => $validated['email'],
                'source' => 'campaign_optin',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'campaign_id' => $campaign->id,
                    'campaign_slug' => $campaign->slug,
                    'campaign_name' => $campaign->name,
                ],
            ]
        );

        $leadEvent = LeadEvent::query()->create([
            'lead_id' => $lead->id,
            'event_type' => 'captured',
            'status' => 'success',
            'payload' => [
                'funnel_id' => $funnel->id,
                'campaign_id' => $campaign->id,
                'campaign_slug' => $campaign->slug,
                'source' => 'campaign_squeeze',
            ],
        ]);

        $enabledIntegrations = $funnel->integrations()
            ->where('enabled', true)
            ->with('integrationAccount:id,provider')
            ->get(['id', 'integration_account_id']);

        foreach ($enabledIntegrations as $funnelIntegration) {
            $provider = $funnelIntegration->integrationAccount?->provider ?? 'unknown';

            DispatchJobLog::query()->create([
                'lead_event_id' => $leadEvent->id,
                'provider' => $provider,
                'status' => 'queued',
                'attempt' => 0,
                'request_payload' => [
                    'funnel_integration_id' => (int) $funnelIntegration->id,
                    'lead_id' => $lead->id,
                    'campaign_id' => $campaign->id,
                    'queued_at' => now()->toIso8601String(),
                ],
                'response_payload' => ['message' => 'Job queued for processing.'],
            ]);

            DispatchLeadToEspJob::dispatch($leadEvent->id, (int) $funnelIntegration->id)
                ->onQueue('esp-dispatch');
        }

        if ($campaign->type === Campaign::TYPE_WEBINAR) {
            $sessionData = [
                'lead_id' => $lead->id,
                'name' => $lead->name,
                'email' => $lead->email,
            ];
            $request->session()->put("funnel_lead.{$funnel->id}", $sessionData);

            $pitch = $this->campaignBuilder->primaryWebinarFunnel($campaign);
            if ($pitch && (int) $pitch->id !== (int) $funnel->id) {
                $request->session()->put("funnel_lead.{$pitch->id}", $sessionData);
            }
        }

        Log::info('[CampaignLeadCapture] Lead synced to /leads + ESP', [
            'campaign_id' => $campaign->id,
            'funnel_id' => $funnel->id,
            'lead_id' => $lead->id,
            'integration_count' => $enabledIntegrations->count(),
        ]);

        return $lead;
    }

    /**
     * Sales campaigns get a lightweight capture funnel for /leads + ESP routing.
     */
    public function ensureOptinFunnel(Campaign $campaign): Funnel
    {
        $existing = $this->findOptinCaptureFunnel($campaign);
        if ($existing) {
            return $existing;
        }

        $template = $this->campaignBuilder->ensureBlankTemplate();
        $version = TemplateVersion::query()
            ->where('template_id', $template->id)
            ->where('is_current', true)
            ->firstOrFail();

        $slugBase = Str::slug($campaign->slug.'-optin');
        $slug = $this->uniqueFunnelSlug((int) $campaign->user_id, $slugBase);

        $funnel = Funnel::query()->create([
            'user_id' => $campaign->user_id,
            'campaign_id' => $campaign->id,
            'template_id' => $template->id,
            'name' => $campaign->name.' — Leads',
            'slug' => $slug,
            'status' => $campaign->status === 'published' ? 'published' : 'draft',
            'published_at' => $campaign->status === 'published' ? now() : null,
            'meta' => [
                'template_version' => $version->version,
                'campaign_variant' => 'optin_capture',
            ],
        ]);

        FunnelPage::query()->create([
            'funnel_id' => $funnel->id,
            'page_type' => 'optin',
            'schema' => ['html' => '', 'css' => '', 'builder' => 'grapesjs'],
            'version' => 1,
        ]);

        $funnel->settings()->create([
            'headline' => $campaign->name,
            'subheadline' => 'Campaign lead capture',
            'chat_mode' => 'simulated',
        ]);

        $meta = $campaign->meta ?? [];
        $meta['primary_lead_funnel_id'] = $funnel->id;
        $campaign->update(['meta' => $meta]);

        return $funnel;
    }

    protected function resolveLeadFunnel(Campaign $campaign): ?Funnel
    {
        if ($campaign->type === Campaign::TYPE_WEBINAR) {
            $funnel = $this->campaignBuilder->primaryOptinFunnel($campaign)
                ?? $this->campaignBuilder->primaryWebinarFunnel($campaign);

            return $funnel?->fresh(['integrations']);
        }

        return $this->ensureOptinFunnel($campaign)->fresh(['integrations']);
    }

    public function syncIntegrationsForFunnel(Funnel $funnel, int $userId): void
    {
        if ($funnel->campaign_id) {
            $campaign = Campaign::query()->find($funnel->campaign_id);
            if ($campaign) {
                app(CampaignAutoresponderService::class)->syncToLeadFunnel($campaign);

                return;
            }
        }

        $this->syncUserIntegrations($funnel, $userId);
    }

    protected function findOptinCaptureFunnel(Campaign $campaign): ?Funnel
    {
        $meta = $campaign->meta ?? [];
        $primaryId = $meta['primary_lead_funnel_id'] ?? null;

        if ($primaryId) {
            $primary = Funnel::query()
                ->where('id', $primaryId)
                ->where('campaign_id', $campaign->id)
                ->first();
            if ($primary) {
                return $primary;
            }
        }

        return Funnel::query()
            ->where('campaign_id', $campaign->id)
            ->where('meta->campaign_variant', 'optin_capture')
            ->first();
    }

    public function resolveLeadFunnelForSync(Campaign $campaign): ?Funnel
    {
        if ($campaign->type === Campaign::TYPE_WEBINAR) {
            return $this->campaignBuilder->primaryOptinFunnel($campaign)
                ?? $this->campaignBuilder->primaryWebinarFunnel($campaign);
        }

        return $this->findOptinCaptureFunnel($campaign) ?? $this->ensureOptinFunnel($campaign);
    }

    /**
     * @deprecated Used only for non-campaign funnels.
     */
    protected function syncUserIntegrations(Funnel $funnel, int $userId): void
    {
        $accountIds = IntegrationAccount::query()
            ->where('user_id', $userId)
            ->pluck('id');

        if ($accountIds->isEmpty()) {
            return;
        }

        $existing = $funnel->integrations()
            ->pluck('integration_account_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($accountIds as $accountId) {
            if (in_array((int) $accountId, $existing, true)) {
                continue;
            }

            FunnelIntegration::query()->create([
                'funnel_id' => $funnel->id,
                'integration_account_id' => $accountId,
                'provider_list_config' => [],
                'enabled' => true,
            ]);
        }
    }

    protected function uniqueFunnelSlug(int $userId, string $base): string
    {
        $slug = $base;
        $i = 1;
        while (Funnel::query()->where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
