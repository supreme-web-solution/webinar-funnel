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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunCampaignQuickStartJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    public function __construct(
        public int $campaignId,
    ) {
        $this->onQueue((string) config('services.campaigns.generation_queue', 'campaign-generate'));
    }

    public function uniqueId(): string
    {
        return "campaign-quick-start:{$this->campaignId}";
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
            $progress->start($campaign, 'quick_start', 'Building full campaign from offer…', [
                'detail' => 'Knowledge → pages → bonuses → emails'.($campaign->type === 'webinar' ? ' → webinar funnels' : ''),
            ]);

            $progress->update($campaign, 'knowledge', 'Pass 1 & 2 — building knowledge base…', 10);
            $knowledgeResult = $knowledge->build($campaign);
            if (! ($knowledgeResult['ok'] ?? false)) {
                $progress->fail($campaign, $knowledgeResult['error'] ?? 'Knowledge build failed.');

                return;
            }
            $generationState->mark($campaign, 'knowledge');

            if ($campaign->type === Campaign::TYPE_WEBINAR) {
                $generationState->mark($campaign, 'lead_magnet_skip');
            } else {
                $progress->update($campaign, 'lead_magnet_suggest', 'Loading lead magnet ideas…', 25);
                $suggest = $leadMagnet->suggest($campaign);
                if (! ($suggest['ok'] ?? false)) {
                    $progress->fail($campaign, $suggest['error'] ?? 'Lead magnet suggestions failed.');

                    return;
                }
                $generationState->mark($campaign, 'lead_magnet_suggest');

                $first = is_array($suggest['suggestions'][0] ?? null) ? $suggest['suggestions'][0] : null;
                $selectedId = (string) ($first['id'] ?? '');
                if ($selectedId === '') {
                    $progress->fail($campaign, 'No lead magnet ideas returned from knowledge.');

                    return;
                }

                $progress->update($campaign, 'lead_magnet', 'Generating lead magnet PDF…', 35);
                $lmResult = $leadMagnet->generate($campaign, $selectedId, 'auto');
                if (! ($lmResult['ok'] ?? false)) {
                    $progress->fail($campaign, $lmResult['error'] ?? 'Lead magnet generation failed.');

                    return;
                }
                $generationState->mark($campaign, 'lead_magnet', ['selected_id' => $selectedId]);
            }

            $progress->update($campaign, 'pages', 'Generating funnel pages…', 50);
            $offer = $campaign->offer_data ?? ['product_name' => $campaign->name];
            if ($campaign->type === Campaign::TYPE_WEBINAR) {
                $content->buildInitialWebinarPages($campaign, $offer, false);
            } else {
                $content->buildInitialSalesPages($campaign, $offer, false);
            }
            $generationState->mark($campaign, 'pages');

            if ($campaign->type === Campaign::TYPE_WEBINAR) {
                $progress->update($campaign, 'webinar', 'Creating registration + pitch/replay funnels…', 62);
                $builder->buildWebinarFunnels($campaign, $offer);
                $generationState->mark($campaign, 'webinar');
            }

            $progress->update($campaign, 'bonuses_suggest', 'Generating bonus ideas…', 72);
            $bonusSuggest = $bonusGenerator->suggest($campaign, 'ebook');
            if (! ($bonusSuggest['ok'] ?? false)) {
                $progress->fail($campaign, $bonusSuggest['error'] ?? 'Bonus suggestions failed.');

                return;
            }
            $generationState->mark($campaign, 'bonuses_suggest', ['bonus_type' => 'ebook']);

            $bonusFirst = is_array($bonusSuggest['suggestions'][0] ?? null) ? $bonusSuggest['suggestions'][0] : null;
            $bonusId = (string) ($bonusFirst['id'] ?? '');
            if ($bonusId === '') {
                $progress->fail($campaign, 'No bonus concepts returned from knowledge.');

                return;
            }

            $progress->update($campaign, 'bonuses', 'Generating ebook bonus…', 82);
            $bonusResult = $bonusGenerator->generate($campaign, $bonusId, 'ebook');
            if (! ($bonusResult['ok'] ?? false)) {
                $progress->fail($campaign, $bonusResult['error'] ?? 'Bonus generation failed.');

                return;
            }
            $generationState->mark($campaign, 'bonuses', ['bonus_type' => 'ebook']);

            $progress->update($campaign, 'emails', 'Writing email swipes…', 90);
            $emailResult = $content->generateEmails($campaign, 'full_launch', 5, false);
            if (! ($emailResult['ok'] ?? false) && empty($emailResult['skipped'])) {
                $progress->fail($campaign, $emailResult['error'] ?? 'Email generation failed.');

                return;
            }
            $generationState->mark($campaign, 'emails');

            $campaign->update(['wizard_step' => max((int) $campaign->wizard_step, 7)]);
            $progress->complete($campaign, 'Campaign ready — review pages, bonuses & publish', [
                'detail' => 'Quick-start generation complete',
            ]);
        } catch (\Throwable $e) {
            Log::error('[CampaignQuickStart] failed', [
                'campaign_id' => $this->campaignId,
                'message' => $e->getMessage(),
            ]);

            $progress->fail($campaign, $e->getMessage());
            throw $e;
        }
    }
}
