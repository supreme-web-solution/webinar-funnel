<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Services\Ai\OpenRouterService;
use Illuminate\Support\Str;

/**
 * Two-pass knowledge builder — all downstream assets read from this base.
 */
class CampaignKnowledgeBuilderService
{
    public function __construct(
        protected OpenRouterService $openRouter,
        protected SalesPageFetcherService $pageFetcher,
        protected CampaignGenerationProgressService $progress,
    ) {}

    /**
     * @return array{ok: bool, knowledge: array<string, mixed>|null, error: string|null}
     */
    public function build(Campaign $campaign): array
    {
        $offer = $campaign->offer_data ?? [];
        $url = (string) ($campaign->offer_url ?? $offer['source_url'] ?? '');
        $pageText = '';

        if ($url !== '') {
            $this->progress->update($campaign, 'fetch', 'Fetching sales page text…', 8, [
                'detail' => $url,
            ]);
            $fetch = $this->pageFetcher->fetch($url);
            if ($fetch['ok'] && is_string($fetch['text'])) {
                $pageText = Str::limit($fetch['text'], 15000);
                $this->progress->update($campaign, 'fetch_done', 'Page text extracted', 12, [
                    'detail' => strlen($pageText).' chars via '.($fetch['method'] ?? 'http'),
                ]);
            }
        }

        $context = json_encode([
            'offer' => $offer,
            'analysis' => $campaign->analysis,
            'affiliate_link' => $campaign->affiliate_link,
            'marketplace' => $campaign->marketplace,
            'campaign_type' => $campaign->type,
            'page_excerpt' => $pageText,
        ], JSON_PRETTY_PRINT);

        $this->progress->update($campaign, 'pass1', 'Pass 1 — AI building research dossier…', 20);

        $pass1 = $this->openRouter->chatJson([
            [
                'role' => 'system',
                'content' => 'You are a senior affiliate strategist building a research dossier. Return ONLY JSON with keys: product_summary, target_audience (array), core_pain_points (array), desired_outcomes (array), unique_mechanism, proof_elements (array), objections (array with objection+rebuttal), competitor_alternatives (array), price_positioning, guarantee_notes, compliance_notes (array), voice_tone, hook_angles (array of 5+ hooks). Be specific and detailed — this feeds all marketing assets.',
            ],
            ['role' => 'user', 'content' => "Build PASS 1 research dossier:\n{$context}"],
        ]);

        if (! $pass1['ok'] || ! is_array($pass1['data'])) {
            return ['ok' => false, 'knowledge' => null, 'error' => $pass1['error'] ?? 'Knowledge pass 1 failed.'];
        }

        $this->progress->update($campaign, 'pass1_done', 'Pass 1 response received', 50, [
            'detail' => count($pass1['data']['core_pain_points'] ?? []).' pain points · '.count($pass1['data']['hook_angles'] ?? []).' hooks extracted',
        ]);

        $this->progress->update($campaign, 'pass2', 'Pass 2 — AI building asset blueprint…', 55);

        $leadMagnetSpec = ' CRITICAL for lead_magnet_ideas (exactly 6): these are opt-in bribes that PRE-SELL THE EXACT PRODUCT from pass1 — NOT generic affiliate marketing education. Every title MUST include the product name OR its unique mechanism (from product_summary / unique_mechanism). Example for a paste-URL funnel builder: "3-Minute Paste-and-Profit Checklist: Squeeze Page + Email Sequence From Any Sales Page" — NOT "Affiliate Marketing Starter Kit" or "5-Minute Funnel Trick" without naming the product. description must say who it is for AND how it leads to buying/using THIS product. outline_bullets must include at least one section about the main product mechanism. why_it_converts must cite a pass1 pain point solved by THIS product.';

        $bonusSpec = ' CRITICAL for bonus_concepts (exactly 5): these are purchase bonuses stacked with the main offer — NOT generic affiliate marketing courses. Every title MUST include the product name OR unique mechanism. promise must explain how this bonus helps the buyer succeed WITH THIS product (not generic "email sequences" or "traffic tips"). Include at least 2 ebook and 2 mini_course types. Example for Automated Commission Machine: "Paste-URL Funnel Playbook: 10 Real Campaigns Built in Under 3 Minutes Each" — NOT "Mastering Automated Email Sequences for Affiliate Success".';

        $pass2Prompt = $campaign->type === 'webinar'
            ? 'You are an affiliate funnel architect. Using the research dossier, return ONLY JSON with keys: lead_magnet_ideas (array of 6 items: id, title, format ebook|checklist|mini_course|quiz|template, description, why_it_converts, outline_bullets), bonus_concepts (array of 5: id, bonus_type ebook|checklist|mini_course|micro_app|swipe_file, title, promise), email_sequence_plan (array of 7 sequential follow-up emails in order: day, angle, subject_hint, goal — each email must build on the previous to drive affiliate link clicks for readers who have not acted yet; angles progress welcome → value → story → proof → objection → urgency → last_chance), webinar_registration_strategy (headline, subheadline, description, cta, bullets array — for a free webinar opt-in page), squeeze_page_strategy (headline, subheadline, bullets array), thankyou_strategy (download_message, bridge_headline, bridge_body), quiz_strategy (title, purpose, question_count), bonus_page_strategy (headline, stack_description). For webinar campaigns, webinar_registration_strategy is the primary registration page copy.'.$leadMagnetSpec.$bonusSpec
            : 'You are an affiliate funnel architect. Using the research dossier, return ONLY JSON with keys: lead_magnet_ideas (array of 6 items: id, title, format ebook|checklist|mini_course|quiz|template, description, why_it_converts, outline_bullets), bonus_concepts (array of 5: id, bonus_type ebook|checklist|mini_course|micro_app|swipe_file, title, promise), email_sequence_plan (array of 7 sequential follow-up emails in order: day, angle, subject_hint, goal — each email must build on the previous to drive affiliate link clicks for readers who have not acted yet; angles progress welcome → value → story → proof → objection → urgency → last_chance), squeeze_page_strategy (headline, subheadline, bullets array), thankyou_strategy (download_message, bridge_headline, bridge_body), quiz_strategy (title, purpose, question_count, questions: REQUIRED array of exactly 4 items — each with question string and options array of 3-4 short answers that qualify the visitor for this specific product), bonus_page_strategy (headline, stack_description).'.$leadMagnetSpec.$bonusSpec;

        $productLabel = (string) ($offer['product_name'] ?? 'Unknown product');
        $mechanism = (string) ($pass1['data']['unique_mechanism'] ?? '');

        $pass2 = $this->openRouter->chatJson([
            [
                'role' => 'system',
                'content' => $pass2Prompt,
            ],
            [
                'role' => 'user',
                'content' => "PASS 1 dossier:\n".json_encode($pass1['data'], JSON_PRETTY_PRINT)
                    ."\n\nProduct context:\n{$context}"
                    ."\n\nCRITICAL — product you are promoting: \"{$productLabel}\"."
                    .($mechanism !== '' ? "\nUnique mechanism: {$mechanism}" : '')
                    ."\nAll lead_magnet_ideas must pre-sell THIS product (name or mechanism in every title). No generic affiliate titles."
                    ."\nAll bonus_concepts must stack with THIS product (name or mechanism in every title). No generic email/traffic/squeeze-page bonuses unless tied to this product.",
            ],
        ]);

        if (! $pass2['ok'] || ! is_array($pass2['data'])) {
            return ['ok' => false, 'knowledge' => null, 'error' => $pass2['error'] ?? 'Knowledge pass 2 failed.'];
        }

        $knowledge = [
            'status' => 'ready',
            'built_at' => now()->toIso8601String(),
            'pass1' => $pass1['data'],
            'pass2' => $pass2['data'],
        ];

        $campaign->update(['knowledge' => $knowledge]);

        return ['ok' => true, 'knowledge' => $knowledge, 'error' => null];
    }

    /**
     * @return array<string, mixed>
     */
    public function contextForGeneration(Campaign $campaign): array
    {
        $k = $campaign->knowledge ?? [];

        return [
            'offer' => $campaign->offer_data ?? [],
            'analysis' => $campaign->analysis ?? [],
            'affiliate_link' => $campaign->affiliate_link,
            'pass1' => $k['pass1'] ?? [],
            'pass2' => $k['pass2'] ?? [],
        ];
    }
}
