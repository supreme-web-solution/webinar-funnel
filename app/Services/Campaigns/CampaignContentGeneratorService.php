<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignBonus;
use App\Models\CampaignEmail;
use App\Models\CampaignPage;
use App\Services\Ai\OpenRouterService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CampaignContentGeneratorService
{
    public function __construct(
        protected OpenRouterService $openRouter,
        protected CampaignKnowledgeBuilderService $knowledge,
        protected TrackedLinkService $trackedLinks,
    ) {}

    /**
     * @return array{ok: bool, suggestions: array<int, array<string, mixed>>, error: string|null}
     */
    public function suggestBonuses(Campaign $campaign): array
    {
        $ctx = $this->knowledge->contextForGeneration($campaign);
        $concepts = $ctx['pass2']['bonus_concepts'] ?? [];

        if (! is_array($concepts) || $concepts === []) {
            $json = $this->openRouter->chatJson([
                [
                    'role' => 'system',
                    'content' => 'Return ONLY JSON {"bonuses":[{"id":"","bonus_type":"ebook|checklist|mini_course|micro_app|swipe_file","title":"","promise":"","why_it_helps":""}]} with 5 bonus options.',
                ],
                ['role' => 'user', 'content' => json_encode($ctx, JSON_PRETTY_PRINT)],
            ]);
            $concepts = $json['ok'] ? ($json['data']['bonuses'] ?? []) : [];
        }

        $meta = $campaign->meta ?? [];
        $meta['bonus_suggestions'] = $concepts;
        $campaign->update(['meta' => $meta]);

        return ['ok' => true, 'suggestions' => is_array($concepts) ? $concepts : [], 'error' => null];
    }

    /**
     * @param  array<int, string>  $selectedIds
     * @return array{ok: bool, count: int, error: string|null}
     */
    public function generateSelectedBonuses(Campaign $campaign, array $selectedIds): array
    {
        $ctx = $this->knowledge->contextForGeneration($campaign);
        $suggestions = collect($campaign->meta['bonus_suggestions'] ?? [])
            ->whereIn('id', $selectedIds)
            ->values();

        if ($suggestions->isEmpty()) {
            return ['ok' => false, 'count' => 0, 'error' => 'No matching bonus selections. Run suggest first.'];
        }

        CampaignBonus::query()->where('campaign_id', $campaign->id)->delete();
        $count = 0;

        foreach ($suggestions as $bonus) {
            $result = $this->openRouter->chatJson([
                [
                    'role' => 'system',
                    'content' => 'Write a comprehensive affiliate bonus deliverable. Return ONLY JSON {"title":"","sections":[{"heading":"","body_markdown":""}]} with 5-8 substantial sections (actionable, 150+ words each). This is a real bonus buyers would pay for — not fluff.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'bonus' => $bonus,
                        'context' => $ctx,
                    ], JSON_PRETTY_PRINT),
                ],
            ]);

            if (! $result['ok'] || ! is_array($result['data'])) {
                continue;
            }

            $markdown = $this->sectionsToMarkdown($result['data']);

            CampaignBonus::query()->create([
                'campaign_id' => $campaign->id,
                'user_id' => $campaign->user_id,
                'title' => (string) ($result['data']['title'] ?? $bonus['title'] ?? 'Bonus'),
                'bonus_type' => (string) ($bonus['bonus_type'] ?? 'ebook'),
                'content' => $markdown,
                'status' => 'ready',
                'meta' => ['selected_id' => $bonus['id'] ?? null],
            ]);
            $count++;
        }

        $this->refreshBonusPage($campaign);

        return ['ok' => $count > 0, 'count' => $count, 'error' => $count === 0 ? 'Bonus generation failed.' : null];
    }

    protected function refreshBonusPage(Campaign $campaign): void
    {
        app(CampaignBonusPresenterService::class)->syncBonusPage($campaign);
    }

    /**
     * @return array{ok: bool, count: int, error: string|null}
     */
    public function generateEmails(Campaign $campaign, string $sequence = 'full_launch', int $count = 5, bool $force = false): array
    {
        if (! $force && $campaign->emails()->exists()) {
            return ['ok' => true, 'count' => $campaign->emails()->count(), 'error' => null, 'skipped' => true];
        }

        $ctx = $this->knowledge->contextForGeneration($campaign);
        $plan = $ctx['pass2']['email_sequence_plan'] ?? [];
        $planSlice = is_array($plan) && $plan !== []
            ? array_slice($plan, 0, $count)
            : $this->defaultEmailPlan($count);

        $emails = [];
        $affiliateLink = (string) ($campaign->affiliate_link ?? '');
        $product = trim((string) ($ctx['pass2']['product_name'] ?? $campaign->offer_data['product_name'] ?? $campaign->name));

        foreach ($planSlice as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $position = $i + 1;
            $total = count($planSlice);
            $previousInSequence = collect($emails)->map(fn (array $email) => [
                'sequence_key' => $email['sequence_key'],
                'subject' => $email['subject'],
                'body_excerpt' => Str::limit(strip_tags((string) $email['body']), 280),
            ])->values()->all();

            $one = $this->openRouter->chatJson([
                [
                    'role' => 'system',
                    'content' => implode("\n", [
                        "You write email {$position} of {$total} in a single affiliate follow-up SEQUENCE for {$product}.",
                        'This is NOT a standalone promo — it continues the same story arc as the previous emails.',
                        'Rules:',
                        '- Reference prior emails naturally when helpful ("As I shared yesterday…", "If you missed my last note…").',
                        '- Same voice, same offer, same knowledge-base facts — do NOT contradict earlier emails.',
                        '- Assume some readers ignored earlier emails: one-sentence recap of the core promise, then new angle.',
                        '- ONE clear click goal per email — include exactly one [AFFILIATE_LINK] CTA near the end.',
                        '- Match the planned angle/goal for this position; escalate urgency toward the final emails.',
                        '- Body: 200-400 words, conversational, plain text with line breaks.',
                        'Return ONLY JSON {"sequence_key":"","subject":"","body":""}.',
                        'sequence_key: short snake_case label (welcome, value, story, proof, objection, urgency, last_chance).',
                    ]),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'email_number' => $position,
                        'total_emails' => $total,
                        'email_plan_item' => $item,
                        'sequence_type' => $sequence,
                        'sequence_arc' => $this->describeSequenceArc($planSlice),
                        'previous_emails_in_sequence' => $previousInSequence,
                        'affiliate_link' => $affiliateLink,
                        'context' => $ctx,
                    ], JSON_PRETTY_PRINT),
                ],
            ]);

            if ($one['ok'] && is_array($one['data'])) {
                $body = str_replace('[AFFILIATE_LINK]', $affiliateLink, (string) ($one['data']['body'] ?? ''));
                $emails[] = [
                    'sequence_key' => (string) ($one['data']['sequence_key'] ?? ($item['angle'] ?? 'email_'.$position)),
                    'subject' => (string) ($one['data']['subject'] ?? ($item['subject_hint'] ?? "Follow-up {$position}")),
                    'body' => $body,
                    'plan_day' => is_numeric($item['day'] ?? null) ? (int) $item['day'] : null,
                ];
            }
        }

        if ($emails === []) {
            return ['ok' => false, 'count' => 0, 'error' => 'Email generation failed. Build knowledge first.'];
        }

        CampaignEmail::query()->where('campaign_id', $campaign->id)->delete();

        foreach ($emails as $i => $email) {
            CampaignEmail::query()->create([
                'campaign_id' => $campaign->id,
                'sequence_key' => $email['sequence_key'],
                'subject' => $email['subject'],
                'body' => $email['body'],
                'sort_order' => $i,
                'meta' => [
                    'sequence' => $sequence,
                    'sequence_position' => $i + 1,
                    'plan_day' => $email['plan_day'] ?? null,
                ],
            ]);
        }

        app(CampaignEmailSequenceService::class)->seedDefaultSchedules(
            $campaign->fresh(),
            $planSlice,
        );

        return ['ok' => true, 'count' => count($emails), 'error' => null];
    }

    /**
     * @return list<array{day: int, angle: string, subject_hint: string, goal: string}>
     */
    protected function defaultEmailPlan(int $count): array
    {
        $templates = [
            ['day' => 0, 'angle' => 'welcome', 'subject_hint' => 'Welcome — here is what you signed up for', 'goal' => 'Deliver on the opt-in promise and introduce the offer softly'],
            ['day' => 1, 'angle' => 'value', 'subject_hint' => 'Quick win you can use today', 'goal' => 'Share one actionable insight from the knowledge base'],
            ['day' => 2, 'angle' => 'story', 'subject_hint' => 'Why I recommend this', 'goal' => 'Personal story that bridges to the affiliate offer'],
            ['day' => 4, 'angle' => 'proof', 'subject_hint' => 'Proof this actually works', 'goal' => 'Social proof and results to build trust'],
            ['day' => 6, 'angle' => 'objection', 'subject_hint' => 'Still on the fence?', 'goal' => 'Handle the main objection and remove friction'],
            ['day' => 8, 'angle' => 'urgency', 'subject_hint' => 'Worth acting on this now', 'goal' => 'Strong reason to click today'],
            ['day' => 10, 'angle' => 'last_chance', 'subject_hint' => 'Last note from me on this', 'goal' => 'Final follow-up with direct CTA for non-responders'],
        ];

        return array_slice($templates, 0, max(1, $count));
    }

    /**
     * @param  list<array<string, mixed>>  $planSlice
     */
    protected function describeSequenceArc(array $planSlice): string
    {
        $parts = [];
        foreach ($planSlice as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $n = $i + 1;
            $angle = (string) ($item['angle'] ?? 'follow_up');
            $goal = (string) ($item['goal'] ?? 'drive clicks');
            $parts[] = "Email {$n} ({$angle}): {$goal}";
        }

        return implode(' → ', $parts);
    }

    /**
     * Webinar campaigns: registration squeeze + bonus page (no thank-you / quiz).
     *
     * @param  array<string, mixed>  $offer
     */
    public function buildInitialWebinarPages(Campaign $campaign, array $offer, bool $force = false): void
    {
        $ctx = $this->knowledge->contextForGeneration($campaign);
        $registration = is_array($ctx['pass2']['webinar_registration_strategy'] ?? null)
            ? $ctx['pass2']['webinar_registration_strategy']
            : ($ctx['pass2']['squeeze_page_strategy'] ?? []);
        $bonusPage = $ctx['pass2']['bonus_page_strategy'] ?? [];

        $product = (string) ($offer['product_name'] ?? $campaign->name);
        $affiliate = (string) ($campaign->affiliate_link ?: '#');
        $affiliateLink = $this->trackedLinks->createForCampaign($campaign, $affiliate, 'Affiliate offer');

        $pages = [
            'squeeze' => [
                'headline' => (string) ($registration['headline'] ?? "Free Webinar: {$product}"),
                'subheadline' => (string) ($registration['subheadline'] ?? 'Register free — instant access to the training room.'),
                'cta' => (string) ($registration['cta'] ?? 'Reserve My Spot'),
                'bullet_points' => $registration['bullets'] ?? [],
                'brand_color' => '#6366f1',
                'page_mode' => 'webinar_registration',
                'editable' => true,
            ],
            'bonus' => [
                'headline' => (string) ($bonusPage['headline'] ?? 'Thanks — your bonuses are ready'),
                'intro' => (string) ($bonusPage['stack_description'] ?? 'Exclusive bonuses when you purchase through our link.'),
                'hero_label' => 'YOUR BONUSES',
                'featured_bonus_uuids' => [],
                'bonuses' => [],
                'affiliate_url' => $affiliateLink->publicUrl(),
                'cta' => 'Get Everything Now',
                'brand_color' => '#ea580c',
                'editable' => true,
            ],
        ];

        foreach ($pages as $type => $content) {
            $existing = CampaignPage::query()
                ->where('campaign_id', $campaign->id)
                ->where('page_type', $type)
                ->first();

            if (! $force && $existing && $this->funnelPageHasContent($existing->content)) {
                continue;
            }

            CampaignPage::query()->updateOrCreate(
                ['campaign_id' => $campaign->id, 'page_type' => $type],
                ['slug' => $type === 'squeeze' ? $campaign->slug : $campaign->slug.'-'.$type, 'content' => $content, 'version' => 1]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public function buildInitialSalesPages(Campaign $campaign, array $offer, bool $force = false): void
    {
        $ctx = $this->knowledge->contextForGeneration($campaign);
        $squeeze = $ctx['pass2']['squeeze_page_strategy'] ?? [];
        $thankyou = $ctx['pass2']['thankyou_strategy'] ?? [];
        $quiz = $ctx['pass2']['quiz_strategy'] ?? [];
        $bonusPage = $ctx['pass2']['bonus_page_strategy'] ?? [];

        $product = (string) ($offer['product_name'] ?? $campaign->name);
        $affiliate = (string) ($campaign->affiliate_link ?: '#');

        $lm = $campaign->pages()->where('page_type', 'lead_magnet')->first();
        $lmTitle = is_array($lm?->content) ? (string) ($lm->content['title'] ?? '') : '';
        $lmDownload = is_array($lm?->content) ? ($lm->content['download_url'] ?? null) : null;
        if (! is_string($lmDownload) && is_array($lm?->content) && is_string($lm->content['download_path'] ?? null)) {
            $lmDownload = Storage::disk('public')->url($lm->content['download_path']);
        }

        $affiliateLink = $this->trackedLinks->createForCampaign($campaign, $affiliate, 'Affiliate offer');
        $quizQuestions = $this->resolveQuizQuestions($campaign, $ctx, $product);

        $pages = [
            'squeeze' => [
                'headline' => (string) ($squeeze['headline'] ?? ($lmTitle !== '' ? "Free: {$lmTitle}" : "Free Guide: {$product}")),
                'subheadline' => (string) ($squeeze['subheadline'] ?? 'Enter your email for instant access.'),
                'cta' => 'Get Instant Access',
                'bullet_points' => $squeeze['bullets'] ?? [],
                'brand_color' => '#4f46e5',
                'video_url' => '',
                'video_title' => '',
                'editable' => true,
            ],
            'thankyou' => [
                'headline' => (string) ($thankyou['download_message'] ?? 'Congrats! Click the link below to download your free gift…'),
                'download_cta' => 'DOWNLOAD NOW',
                'download_url' => is_string($lmDownload) ? $lmDownload : null,
                'download_label' => $lmTitle !== '' ? $lmTitle : 'Your free guide',
                'bridge_headline' => (string) ($thankyou['bridge_headline'] ?? 'What Next?'),
                'bridge_body' => (string) ($thankyou['bridge_body'] ?? "Discover how {$product} can help you get results faster."),
                'bridge_cta' => 'Click Here To Discover More',
                'bridge_url' => $affiliateLink->publicUrl(),
                'brand_color' => '#4f46e5',
                'editable' => true,
            ],
            'quiz' => [
                'title' => (string) ($quiz['title'] ?? 'Quick Quiz'),
                'intro' => (string) ($quiz['purpose'] ?? 'Answer a few questions to get your personalized guide.'),
                'questions' => $quizQuestions,
                'result_headline' => 'Your results are ready!',
                'result_body' => 'Download your guide on the next page.',
                'editable' => true,
            ],
            'bonus' => [
                'headline' => (string) ($bonusPage['headline'] ?? 'Thanks — your bonuses are ready'),
                'intro' => (string) ($bonusPage['stack_description'] ?? 'Below are the exclusive bonuses included with your purchase. Download or open each one below.'),
                'hero_label' => 'YOUR BONUSES',
                'featured_bonus_uuids' => [],
                'bonuses' => [],
                'affiliate_url' => $affiliateLink->publicUrl(),
                'cta' => 'Get Everything Now',
                'brand_color' => '#ea580c',
                'editable' => true,
            ],
        ];

        foreach ($pages as $type => $content) {
            $existing = CampaignPage::query()
                ->where('campaign_id', $campaign->id)
                ->where('page_type', $type)
                ->first();

            if (! $force && $existing && $this->funnelPageHasContent($existing->content)) {
                if ($type === 'thankyou' && is_string($lmDownload)) {
                    $merged = $existing->content ?? [];
                    $merged['download_url'] = $lmDownload;
                    $merged['download_label'] = $lmTitle !== '' ? $lmTitle : ($merged['download_label'] ?? 'Your free guide');
                    $existing->update(['content' => $merged]);
                }

                continue;
            }

            CampaignPage::query()->updateOrCreate(
                ['campaign_id' => $campaign->id, 'page_type' => $type],
                ['slug' => $type === 'squeeze' ? $campaign->slug : $campaign->slug.'-'.$type, 'content' => $content, 'version' => 1]
            );
        }
    }

    /**
     * @return array<int, array{question: string, options: array<int, string>}>
     */
    protected function resolveQuizQuestions(Campaign $campaign, array $ctx, string $product): array
    {
        $quiz = is_array($ctx['pass2']['quiz_strategy'] ?? null) ? $ctx['pass2']['quiz_strategy'] : [];
        $raw = is_array($quiz['questions'] ?? null) ? $quiz['questions'] : [];
        $fromKnowledge = $this->extractValidQuizQuestions($raw);

        if (count($fromKnowledge) >= 3) {
            return $fromKnowledge;
        }

        $generated = $this->generateQuizQuestionsFromKnowledge($campaign, $ctx, $product);
        $merged = $this->extractValidQuizQuestions(array_merge($fromKnowledge, $generated));

        return $this->normalizeQuizQuestions($merged, $product);
    }

    /**
     * @return list<array{question: string, options: array<int, string>}>
     */
    protected function generateQuizQuestionsFromKnowledge(Campaign $campaign, array $ctx, string $product): array
    {
        $result = $this->openRouter->chatJson([
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'Create a lead-qualification quiz for an affiliate funnel.',
                    'Return ONLY JSON {"questions":[{"question":"","options":["","",""]}]} with exactly 4 questions.',
                    'Each question must relate to the product/offer in the knowledge base.',
                    'Each question needs 3-4 short answer options (no emojis).',
                    'Questions should qualify the visitor and lead naturally toward the offer.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'product' => $product,
                    'affiliate_link' => $campaign->affiliate_link,
                    'context' => $ctx,
                ], JSON_PRETTY_PRINT),
            ],
        ]);

        if (! $result['ok'] || ! is_array($result['data']['questions'] ?? null)) {
            return [];
        }

        return $this->extractValidQuizQuestions($result['data']['questions']);
    }

    /**
     * @param  array<int, mixed>  $raw
     * @return list<array{question: string, options: array<int, string>}>
     */
    protected function extractValidQuizQuestions(array $raw): array
    {
        $out = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $question = trim((string) ($row['question'] ?? ''));
            $options = array_values(array_filter(array_map(
                fn ($option) => is_string($option) ? trim($option) : '',
                is_array($row['options'] ?? null) ? $row['options'] : [],
            )));

            if ($question === '' || count($options) < 2) {
                continue;
            }

            $out[] = [
                'question' => $question,
                'options' => array_slice($options, 0, 5),
            ];

            if (count($out) >= 5) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $raw
     * @return array<int, array{question: string, options: array<int, string>}>
     */
    protected function normalizeQuizQuestions(array $raw, string $product): array
    {
        $out = $this->extractValidQuizQuestions($raw);

        if (count($out) >= 2) {
            return $out;
        }

        return [
            [
                'question' => "What best describes your experience with topics related to {$product}?",
                'options' => ['Just getting started', 'Some experience', 'Pretty advanced'],
            ],
            [
                'question' => "What's your main goal right now?",
                'options' => ['Save time', 'Make more money', 'Learn faster', 'Fix a specific problem'],
            ],
            [
                'question' => 'How soon are you looking to take action?',
                'options' => ['This week', 'This month', 'Just researching'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $content
     */
    protected function funnelPageHasContent(?array $content): bool
    {
        if (! is_array($content)) {
            return false;
        }

        return ! empty($content['headline']) || ! empty($content['title']) || ! empty($content['subheadline']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function sectionsToMarkdown(array $data): string
    {
        $out = '# '.($data['title'] ?? 'Bonus')."\n\n";
        foreach ($data['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }
            $out .= '## '.($section['heading'] ?? 'Section')."\n\n";
            $out .= ($section['body_markdown'] ?? '')."\n\n";
        }

        return trim($out);
    }
}
