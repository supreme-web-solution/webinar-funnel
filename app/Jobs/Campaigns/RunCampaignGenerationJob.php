<?php

namespace App\Jobs\Campaigns;

use App\Models\Campaign;
use App\Services\Campaigns\BonusGeneratorService;
use App\Services\Campaigns\CampaignBuilderService;
use App\Services\Campaigns\CampaignContentGeneratorService;
use App\Services\Campaigns\CampaignGenerationProgressService;
use App\Services\Campaigns\CampaignGenerationStateService;
use App\Services\Campaigns\CampaignKnowledgeBuilderService;
use App\Services\Campaigns\LeadMagnetGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunCampaignGenerationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900;

    /** @var array<int, int> */
    public array $backoff = [30];

    public int $uniqueFor = 900;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $campaignId,
        public string $step,
        public array $payload = [],
    ) {
        $this->onQueue((string) config('services.campaigns.generation_queue', 'campaign-generate'));
    }

    public function uniqueId(): string
    {
        return "campaign-gen:{$this->campaignId}:{$this->step}";
    }

    public function handle(
        CampaignGenerationProgressService $progress,
        CampaignGenerationStateService $generationState,
        CampaignKnowledgeBuilderService $knowledge,
        LeadMagnetGeneratorService $leadMagnet,
        CampaignContentGeneratorService $content,
        BonusGeneratorService $bonusGenerator,
        CampaignBuilderService $builder,
    ): void {
        $campaign = Campaign::query()->find($this->campaignId);

        if (! $campaign) {
            return;
        }

        try {
            match ($this->step) {
                'knowledge' => $this->runKnowledge($campaign, $progress, $generationState, $knowledge),
                'lead_magnet_suggest' => $this->runLeadMagnetSuggest($campaign, $progress, $generationState, $leadMagnet),
                'lead_magnet' => $this->runLeadMagnet($campaign, $progress, $generationState, $leadMagnet),
                'pages' => $this->runPages($campaign, $progress, $generationState, $content),
                'bonuses_suggest' => $this->runBonusesSuggest($campaign, $progress, $generationState, $bonusGenerator),
                'bonuses' => $this->runBonuses($campaign, $progress, $generationState, $bonusGenerator),
                'emails' => $this->runEmails($campaign, $progress, $generationState, $content),
                'webinar' => $this->runWebinar($campaign, $progress, $generationState, $builder),
                default => throw new \InvalidArgumentException("Unknown generation step: {$this->step}"),
            };
        } catch (\Throwable $e) {
            Log::error('[CampaignGeneration] failed', [
                'campaign_id' => $this->campaignId,
                'step' => $this->step,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $progress->update($campaign, 'retry', 'Temporary error — retrying…', (int) (($progress->get($campaign)['progress'] ?? 0)), [
                    'detail' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $campaign = Campaign::query()->find($this->campaignId);
        if (! $campaign) {
            return;
        }

        app(CampaignGenerationProgressService::class)->fail(
            $campaign,
            $exception?->getMessage() ?? 'Generation failed after retries.'
        );
    }

    protected function runKnowledge(Campaign $campaign, CampaignGenerationProgressService $progress, CampaignGenerationStateService $generationState, CampaignKnowledgeBuilderService $knowledge): void
    {
        $progress->update($campaign, 'fetch', 'Fetching sales page text for dossier…', 5, [
            'detail' => 'Jina Reader / direct HTTP',
        ]);

        $progress->update($campaign, 'pass1', 'Pass 1 — AI research dossier (pain points, hooks, audience)…', 15);

        $result = $knowledge->build($campaign);

        if (! $result['ok']) {
            $progress->fail($campaign, $result['error'] ?? 'Knowledge build failed.');

            return;
        }

        $progress->update($campaign, 'pass2_done', 'Pass 2 complete — asset blueprint extracted', 95, [
            'detail' => 'Lead magnet ideas, squeeze strategy, email plan ready',
            'extracted' => [
                'lead_magnet_ideas' => count($result['knowledge']['pass2']['lead_magnet_ideas'] ?? []),
                'bonus_concepts' => count($result['knowledge']['pass2']['bonus_concepts'] ?? []),
            ],
        ]);

        $campaign->update(['wizard_step' => max((int) $campaign->wizard_step, 3)]);
        $generationState->mark($campaign, 'knowledge');
        $progress->complete($campaign, 'Knowledge base ready — 2 AI passes complete');
    }

    protected function runLeadMagnetSuggest(Campaign $campaign, CampaignGenerationProgressService $progress, CampaignGenerationStateService $generationState, LeadMagnetGeneratorService $leadMagnet): void
    {
        $progress->update($campaign, 'suggest', 'Extracting lead magnet ideas from knowledge pass 2…', 20);

        $result = $leadMagnet->suggest($campaign);

        if (! $result['ok']) {
            $progress->fail($campaign, $result['error'] ?? 'Could not load lead magnet suggestions.');

            return;
        }

        $generationState->mark($campaign, 'lead_magnet_suggest');
        $progress->complete($campaign, count($result['suggestions']).' lead magnet ideas loaded', [
            'detail' => 'Top recommendation auto-selected',
        ]);
    }

    protected function runLeadMagnet(Campaign $campaign, CampaignGenerationProgressService $progress, CampaignGenerationStateService $generationState, LeadMagnetGeneratorService $leadMagnet): void
    {
        $selectedId = (string) ($this->payload['selected_id'] ?? '');
        $mode = (string) ($this->payload['mode'] ?? 'auto');

        $progress->update($campaign, 'init', 'Loading knowledge dossier + selected concept…', 5, [
            'selected_id' => $selectedId,
        ]);

        $result = $leadMagnet->generate($campaign, $selectedId, $mode);

        if (! $result['ok']) {
            $progress->fail($campaign, $result['error'] ?? 'Lead magnet generation failed.');

            return;
        }

        if (! empty($result['skipped'])) {
            $generationState->mark($campaign, 'lead_magnet', [
                'selected_id' => $selectedId,
            ]);
            $progress->complete($campaign, 'Lead magnet already saved — no regeneration needed', [
                'detail' => 'Showing existing document',
            ]);

            return;
        }

        $content = $result['content'] ?? [];
        $campaign->update(['wizard_step' => max((int) $campaign->wizard_step, 4)]);
        $generationState->mark($campaign, 'lead_magnet', [
            'selected_id' => $selectedId,
        ]);

        $progress->complete($campaign, 'Lead magnet ready — '.($content['page_count'] ?? '?').' designed pages', [
            'detail' => 'Printable HTML saved · thank-you download wired',
            'pages_done' => $content['page_count'] ?? 0,
            'pages_total' => $content['page_count'] ?? 0,
        ]);
    }

    protected function runPages(Campaign $campaign, CampaignGenerationProgressService $progress, CampaignGenerationStateService $generationState, CampaignContentGeneratorService $content): void
    {
        $progress->update($campaign, 'pages', $campaign->type === 'webinar'
            ? 'Building webinar registration & bonus pages from knowledge…'
            : 'Building squeeze, thank-you, quiz & bonus from knowledge…', 30, [
                'detail' => $campaign->type === 'webinar'
                    ? 'Registration page copy from knowledge — opt-in goes straight to webinar room'
                    : 'Using squeeze strategy + lead magnet download URL',
            ]);

        if ($campaign->type === 'webinar') {
            $content->buildInitialWebinarPages(
                $campaign,
                $campaign->offer_data ?? ['product_name' => $campaign->name],
                (bool) ($this->payload['force'] ?? false),
            );

            $builder = app(CampaignBuilderService::class);
            $offer = $campaign->offer_data ?? ['product_name' => $campaign->name];
            $builder->buildWebinarFunnels($campaign->fresh(), $offer);
            $generationState->mark($campaign, 'webinar');
        } else {
            $content->buildInitialSalesPages(
                $campaign,
                $campaign->offer_data ?? ['product_name' => $campaign->name],
                (bool) ($this->payload['force'] ?? false),
            );
        }
        $campaign->update(['wizard_step' => max((int) $campaign->wizard_step, 5)]);
        $generationState->mark($campaign, 'pages');

        $progress->complete($campaign, $campaign->type === 'webinar'
            ? 'Webinar pages + registration & pitch funnels ready'
            : '4 funnel pages generated', [
                'detail' => $campaign->type === 'webinar'
                    ? 'Branded squeeze · Registration funnel · Pitch/replay room · Bonus page'
                    : 'Squeeze · Thank you · Quiz · Bonus',
            ]);
    }

    protected function runBonusesSuggest(Campaign $campaign, CampaignGenerationProgressService $progress, CampaignGenerationStateService $generationState, BonusGeneratorService $bonusGenerator): void
    {
        $bonusType = (string) ($this->payload['bonus_type'] ?? 'ebook');
        $progress->update($campaign, 'bonuses_suggest', 'Loading '.$bonusType.' concepts from knowledge…', 20);

        $result = $bonusGenerator->suggest($campaign, $bonusType);

        if (! $result['ok']) {
            $progress->fail($campaign, $result['error'] ?? 'Could not load bonus options.');

            return;
        }

        $generationState->mark($campaign, 'bonuses_suggest', [
            'bonus_type' => $bonusType,
        ]);
        $progress->complete($campaign, count($result['suggestions']).' bonus ideas ready — pick one', [
            'detail' => 'Choose a concept, then click Generate',
        ]);
    }

    protected function runBonuses(Campaign $campaign, CampaignGenerationProgressService $progress, CampaignGenerationStateService $generationState, BonusGeneratorService $bonusGenerator): void
    {
        $selectedId = (string) ($this->payload['selected_id'] ?? '');
        $bonusType = (string) ($this->payload['bonus_type'] ?? 'ebook');

        $progress->update($campaign, 'bonuses', 'Generating '.$bonusType.' bonus (dedicated AI pass)…', 40);

        $result = $bonusGenerator->generate($campaign, $selectedId, $bonusType);

        if (! $result['ok']) {
            $progress->fail($campaign, $result['error'] ?? 'Bonus generation failed.');

            return;
        }

        $campaign->update(['wizard_step' => max((int) $campaign->wizard_step, 6)]);
        $generationState->mark($campaign, 'bonuses', [
            'selected_id' => $selectedId,
            'bonus_type' => $bonusType,
        ]);
        $progress->complete($campaign, 'Bonus ready — '.($result['bonus']?->title ?? 'generated'));
    }

    protected function runEmails(Campaign $campaign, CampaignGenerationProgressService $progress, CampaignGenerationStateService $generationState, CampaignContentGeneratorService $content): void
    {
        $count = (int) ($this->payload['count'] ?? 5);
        $force = (bool) ($this->payload['force'] ?? false);
        $progress->update($campaign, 'emails', "Writing {$count} email swipes from knowledge plan…", 50, [
            'detail' => 'One dedicated AI call per email',
        ]);

        $result = $content->generateEmails($campaign, $this->payload['sequence'] ?? 'full_launch', $count, $force);

        if (! empty($result['skipped'])) {
            $generationState->mark($campaign, 'emails');
            $progress->complete($campaign, ($result['count'] ?? 0).' email swipes already saved', [
                'detail' => 'No regeneration needed',
            ]);

            return;
        }

        if (! $result['ok']) {
            $progress->fail($campaign, $result['error'] ?? 'Email generation failed.');

            return;
        }

        $campaign->update(['wizard_step' => max((int) $campaign->wizard_step, 7)]);
        $generationState->mark($campaign, 'emails');
        $progress->complete($campaign, ($result['count'] ?? 0).' email swipes ready');
    }

    protected function runWebinar(Campaign $campaign, CampaignGenerationProgressService $progress, CampaignGenerationStateService $generationState, CampaignBuilderService $builder): void
    {
        $progress->update($campaign, 'webinar', 'Creating registration + pitch/replay funnels…', 60);

        $offer = $campaign->offer_data ?? ['product_name' => $campaign->name];
        $funnels = $builder->buildWebinarFunnels($campaign, $offer);

        $generationState->mark($campaign, 'webinar');
        $progress->complete($campaign, count($funnels).' webinar funnels ready — add video URL on pitch room', [
            'detail' => 'Registration funnel · Pitch & replay room',
        ]);
    }
}
