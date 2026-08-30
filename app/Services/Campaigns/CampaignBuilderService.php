<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignBonus;
use App\Models\CampaignEmail;
use App\Models\CampaignPage;
use App\Models\ChatRoom;
use App\Models\Funnel;
use App\Models\FunnelPage;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Services\Ai\OpenRouterService;
use Illuminate\Support\Str;

class CampaignBuilderService
{
    public function __construct(
        protected OpenRouterService $openRouter,
        protected TrackedLinkService $trackedLinks,
        protected CampaignKnowledgeBuilderService $knowledge,
    ) {}

    public function ensureBlankTemplate(): Template
    {
        $template = Template::query()->firstOrCreate(
            ['slug' => 'affiliateos-blank'],
            [
                'name' => 'AffiliateOS Blank',
                'category' => 'system',
                'conversion_style' => 'webinar',
                'is_active' => true,
                'sort_order' => 9999,
                'thumbnail_url' => null,
                'vendor_contact' => null,
            ]
        );

        TemplateVersion::query()->firstOrCreate(
            [
                'template_id' => $template->id,
                'version' => 1,
            ],
            [
                'is_current' => true,
                'optin_schema' => $this->defaultOptinSchema(),
                'webinar_schema' => ['blocks' => []],
                'default_settings' => [
                    'chat_mode' => 'simulated',
                    'allow_replay' => true,
                    'video_url' => '',
                    'headline' => 'Watch This Training',
                    'subheadline' => 'Learn how to get results with this offer.',
                ],
            ]
        );

        return $template->fresh();
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public function buildSalesPages(Campaign $campaign, array $offer): void
    {
        $product = (string) ($offer['product_name'] ?? $campaign->name);
        $headline = (string) ($offer['headline'] ?? "Get {$product}");
        $sub = (string) ($offer['subheadline'] ?? ($offer['description'] ?? ''));
        $affiliate = (string) ($campaign->affiliate_link ?: '#');

        $affiliateLink = $this->trackedLinks->createForCampaign(
            $campaign,
            $affiliate,
            'Affiliate offer'
        );
        $downloadStub = $this->trackedLinks->createForCampaign(
            $campaign,
            url('/campaigns/'.$campaign->uuid.'/download-placeholder'),
            'Lead magnet download'
        );

        $pages = [
            'squeeze' => [
                'headline' => $headline ?: "Free Training: {$product}",
                'subheadline' => $sub ?: 'Enter your email to get instant access.',
                'cta' => 'Get Instant Access',
                'bullet_points' => [
                    'Discover the core promise of '.$product,
                    'Get a free bonus guide instantly',
                    'See the next step to go deeper',
                ],
                'brand_color' => '#4f46e5',
            ],
            'thankyou' => [
                'headline' => 'Congrats! Click the link below to download your free gift…',
                'download_cta' => 'DOWNLOAD NOW',
                'download_url' => $downloadStub->publicUrl(),
                'bridge_headline' => 'What Next?',
                'bridge_body' => "Click here to discover how {$product} can help you get results faster.",
                'bridge_cta' => 'Continue to the Offer',
                'bridge_url' => $affiliateLink->publicUrl(),
                'brand_color' => '#4f46e5',
            ],
            'bonus' => [
                'headline' => "Exclusive Bonuses for {$product}",
                'intro' => 'Claim these bonuses when you purchase through our link.',
                'bonuses' => [],
                'affiliate_url' => $affiliateLink->publicUrl(),
                'cta' => 'Get Everything Now',
                'brand_color' => '#4f46e5',
            ],
            'lead_magnet' => [
                'title' => "Quick-Start Guide for {$product}",
                'summary' => 'A short how-to guide that warms leads before the offer.',
            ],
        ];

        foreach ($pages as $type => $content) {
            CampaignPage::query()->updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'page_type' => $type,
                ],
                [
                    'slug' => $type === 'squeeze' ? $campaign->slug : $campaign->slug.'-'.$type,
                    'content' => $content,
                    'version' => 1,
                ]
            );
        }
    }

    /**
     * Pitch / replay room funnel (video, chat, CTA, replay).
     */
    public function primaryWebinarFunnel(Campaign $campaign): ?Funnel
    {
        $meta = $campaign->meta ?? [];
        $primaryId = $meta['primary_webinar_funnel_id'] ?? null;

        if ($primaryId) {
            $primary = $campaign->funnels()->find($primaryId);
            if ($primary) {
                return $primary;
            }
        }

        $funnels = $campaign->funnels()->get();
        if ($funnels->isEmpty()) {
            return null;
        }

        $preferred = $funnels->first(function (Funnel $funnel): bool {
            $variant = (string) ($funnel->meta['campaign_variant'] ?? '');
            if (in_array($variant, ['webinar_pitch', 'webinar'], true)) {
                return true;
            }

            $label = strtolower($funnel->name.' '.$funnel->slug);

            return (str_contains($label, 'webinar') || str_contains($label, 'pitch') || str_contains($label, 'room'))
                && ! str_contains($label, 'registration');
        });

        return $preferred ?? $funnels->first();
    }

    /**
     * Registration / opt-in funnel (captures email, routes to pitch room).
     */
    public function primaryOptinFunnel(Campaign $campaign): ?Funnel
    {
        $meta = $campaign->meta ?? [];
        $primaryId = $meta['primary_optin_funnel_id'] ?? null;

        if ($primaryId) {
            $primary = $campaign->funnels()->find($primaryId);
            if ($primary) {
                return $primary;
            }
        }

        return $campaign->funnels()->get()->first(function (Funnel $funnel): bool {
            $variant = (string) ($funnel->meta['campaign_variant'] ?? '');

            return $variant === 'webinar_optin' || str_contains(strtolower($funnel->slug), '-registration');
        });
    }

    /**
     * Create registration + pitch/replay funnels for a webinar campaign.
     *
     * @param  array<string, mixed>  $offer
     * @return array<int, Funnel>
     */
    public function buildWebinarFunnels(Campaign $campaign, array $offer): array
    {
        $pitch = $this->primaryWebinarFunnel($campaign);
        $optin = $this->primaryOptinFunnel($campaign);

        if ($pitch && ! $optin) {
            $this->normalizeLegacyPitchFunnel($pitch);
            $optin = $this->createOptinFunnel($campaign, $offer, $pitch);
        }

        if (! $pitch) {
            $pitch = $this->createPitchFunnel($campaign, $offer);
        }

        if (! $optin) {
            $optin = $this->createOptinFunnel($campaign, $offer, $pitch);
        }

        $this->linkOptinToPitch($optin, $pitch);
        $this->hydrateWebinarFunnelSettings($campaign, $pitch, $offer);

        $meta = $campaign->meta ?? [];
        $meta['primary_webinar_funnel_id'] = $pitch->id;
        $meta['primary_optin_funnel_id'] = $optin->id;
        $campaign->update(['meta' => $meta]);

        return [$optin->fresh(['settings']), $pitch->fresh(['settings'])];
    }

    protected function normalizeLegacyPitchFunnel(Funnel $funnel): void
    {
        $meta = $funnel->meta ?? [];
        if (($meta['campaign_variant'] ?? '') === 'webinar_pitch') {
            return;
        }

        $meta['campaign_variant'] = 'webinar_pitch';
        $funnel->update([
            'name' => str_contains($funnel->name, 'Pitch') ? $funnel->name : $funnel->name.' — Pitch & Replay',
            'meta' => $meta,
        ]);

        $funnel->pages()->where('page_type', 'optin')->delete();
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function createPitchFunnel(Campaign $campaign, array $offer): Funnel
    {
        $template = $this->ensureBlankTemplate();
        $version = $template->versions()->where('is_current', true)->firstOrFail();
        $ctx = $this->knowledge->contextForGeneration($campaign);
        $registration = $this->webinarRegistrationFromContext($ctx, $offer, $campaign->name);

        $slug = $this->uniqueFunnelSlug((int) $campaign->user_id, Str::slug($campaign->slug.'-webinar'));

        $funnel = Funnel::query()->create([
            'user_id' => $campaign->user_id,
            'campaign_id' => $campaign->id,
            'template_id' => $template->id,
            'name' => $campaign->name.' — Pitch & Replay',
            'slug' => $slug,
            'status' => 'draft',
            'meta' => [
                'template_version' => $version->version,
                'campaign_variant' => 'webinar_pitch',
            ],
        ]);

        FunnelPage::query()->create([
            'funnel_id' => $funnel->id,
            'page_type' => 'webinar',
            'schema' => ['blocks' => []],
            'version' => 1,
        ]);

        $affiliateTracked = filled($campaign->affiliate_link)
            ? $this->trackedLinks->createForCampaign($campaign, (string) $campaign->affiliate_link, $campaign->name.' affiliate', $funnel->id)
            : null;

        $settings = $this->webinarSettingsPayload($campaign, $offer, $registration, $affiliateTracked?->publicUrl());
        $funnel->settings()->create($settings);

        ChatRoom::query()->create([
            'funnel_id' => $funnel->id,
            'mode' => 'simulated',
            'is_active' => true,
        ]);

        return $funnel;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function createOptinFunnel(Campaign $campaign, array $offer, Funnel $pitchFunnel): Funnel
    {
        $template = $this->ensureBlankTemplate();
        $version = $template->versions()->where('is_current', true)->firstOrFail();
        $ctx = $this->knowledge->contextForGeneration($campaign);
        $registration = $this->webinarRegistrationFromContext($ctx, $offer, $campaign->name);
        $product = (string) ($offer['product_name'] ?? $campaign->name);

        $slug = $this->uniqueFunnelSlug((int) $campaign->user_id, Str::slug($campaign->slug.'-registration'));

        $funnel = Funnel::query()->create([
            'user_id' => $campaign->user_id,
            'campaign_id' => $campaign->id,
            'template_id' => $template->id,
            'name' => $campaign->name.' — Registration',
            'slug' => $slug,
            'status' => 'draft',
            'meta' => [
                'template_version' => $version->version,
                'campaign_variant' => 'webinar_optin',
                'pitch_funnel_id' => $pitchFunnel->id,
                'pitch_funnel_slug' => $pitchFunnel->slug,
            ],
        ]);

        $optinSchema = $this->defaultOptinSchema();
        $optinSchema['html'] = str_replace(
            ['{{HEADLINE}}', '{{SUBHEADLINE}}', '{{PRODUCT}}', 'Reserve My Spot'],
            [
                e($registration['headline']),
                e($registration['subheadline']),
                e($product),
                e($registration['cta']),
            ],
            $optinSchema['html']
        );

        FunnelPage::query()->create([
            'funnel_id' => $funnel->id,
            'page_type' => 'optin',
            'schema' => $optinSchema,
            'version' => 1,
        ]);

        $funnel->settings()->create([
            'headline' => $registration['headline'],
            'subheadline' => $registration['subheadline'],
            'chat_mode' => 'simulated',
            'allow_replay' => false,
        ]);

        app(CampaignLeadCaptureService::class)->syncIntegrationsForFunnel($funnel, (int) $campaign->user_id);

        return $funnel;
    }

    protected function linkOptinToPitch(Funnel $optin, Funnel $pitch): void
    {
        $meta = $optin->meta ?? [];
        $meta['pitch_funnel_id'] = $pitch->id;
        $meta['pitch_funnel_slug'] = $pitch->slug;
        $optin->update(['meta' => $meta]);
    }

    /**
     * Re-apply knowledge-based webinar settings to an existing funnel (e.g. after knowledge rebuild).
     *
     * @param  array<string, mixed>  $offer
     */
    public function hydrateWebinarFunnelSettings(Campaign $campaign, Funnel $funnel, array $offer): void
    {
        $ctx = $this->knowledge->contextForGeneration($campaign);
        $registration = $this->webinarRegistrationFromContext($ctx, $offer, $campaign->name);

        $affiliateTracked = filled($campaign->affiliate_link)
            ? $this->trackedLinks->createForCampaign($campaign, (string) $campaign->affiliate_link, $campaign->name.' affiliate', $funnel->id)
            : null;

        $settings = $this->webinarSettingsPayload($campaign, $offer, $registration, $affiliateTracked?->publicUrl());
        $funnel->settings()->updateOrCreate(['funnel_id' => $funnel->id], $settings);

        $optin = $this->primaryOptinFunnel($campaign);
        if ($optin) {
            $this->syncCampaignWebinarRegistrationPage(
                $campaign,
                $optin,
                $funnel,
                $registration,
                $affiliateTracked?->publicUrl(),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $offer
     * @return array{headline: string, subheadline: string, description: string, cta: string, bullets: array<int, string>}
     */
    protected function webinarRegistrationFromContext(array $ctx, array $offer, string $campaignName): array
    {
        $pass2 = $ctx['pass2'] ?? [];
        $pass1 = $ctx['pass1'] ?? [];
        $webinar = is_array($pass2['webinar_registration_strategy'] ?? null) ? $pass2['webinar_registration_strategy'] : [];
        $squeeze = is_array($pass2['squeeze_page_strategy'] ?? null) ? $pass2['squeeze_page_strategy'] : [];

        $product = (string) ($offer['product_name'] ?? $campaignName);
        $headline = (string) ($webinar['headline'] ?? $squeeze['headline'] ?? $offer['headline'] ?? "Free Webinar: {$product}");
        $subheadline = (string) ($webinar['subheadline'] ?? $squeeze['subheadline'] ?? $offer['subheadline'] ?? 'Register free — watch the training instantly.');
        $description = (string) ($webinar['description'] ?? $offer['description'] ?? $pass1['product_summary'] ?? $subheadline);
        $bullets = $webinar['bullets'] ?? $squeeze['bullets'] ?? $pass1['hook_angles'] ?? [];
        if (! is_array($bullets)) {
            $bullets = [];
        }

        return [
            'headline' => $headline,
            'subheadline' => $subheadline,
            'description' => Str::limit(strip_tags($description), 600),
            'cta' => (string) ($webinar['cta'] ?? 'Reserve My Spot'),
            'bullets' => array_values(array_map('strval', array_slice($bullets, 0, 5))),
        ];
    }

    /**
     * @param  array{headline: string, subheadline: string, description: string, cta: string, bullets: array<int, string>}  $registration
     * @return array<string, mixed>
     */
    protected function webinarSettingsPayload(Campaign $campaign, array $offer, array $registration, ?string $affiliatePublicUrl): array
    {
        $product = (string) ($offer['product_name'] ?? $campaign->name);
        $ctaUrl = $affiliatePublicUrl ?: (string) ($campaign->affiliate_link ?? '');

        return [
            'headline' => $registration['headline'],
            'subheadline' => $registration['subheadline'],
            'webinar_title' => $registration['headline'],
            'webinar_description' => $registration['description'],
            'webinar_cta_label' => 'Claim Your Spot',
            'webinar_cta_url' => $ctaUrl,
            'affiliate_request_link' => $campaign->affiliate_link,
            'video_url' => '',
            'webinar_duration_seconds' => null,
            'offer_name' => $product,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
            'webinar_ai_enabled' => false,
            'webinar_ai_auto_reply_enabled' => true,
        ];
    }

    /**
     * @param  array{headline: string, subheadline: string, description: string, cta: string, bullets: array<int, string>}  $registration
     */
    protected function syncCampaignWebinarRegistrationPage(
        Campaign $campaign,
        Funnel $optinFunnel,
        Funnel $pitchFunnel,
        array $registration,
        ?string $affiliatePublicUrl,
    ): void {
        $campaign->loadMissing('user');
        $username = $campaign->user?->username ?? 'user';

        $webinarRoomUrl = route('public.webinar', ['username' => $username, 'slug' => $pitchFunnel->slug]);
        $optinUrl = route('public.optin', ['username' => $username, 'slug' => $optinFunnel->slug]);
        $squeezeUrl = route('public.campaign.page', ['username' => $username, 'slug' => $campaign->slug, 'page' => 'squeeze']);

        CampaignPage::query()->updateOrCreate(
            ['campaign_id' => $campaign->id, 'page_type' => 'squeeze'],
            [
                'slug' => $campaign->slug,
                'content' => [
                    'headline' => $registration['headline'],
                    'subheadline' => $registration['subheadline'],
                    'cta' => $registration['cta'],
                    'bullet_points' => $registration['bullets'],
                    'brand_color' => '#6366f1',
                    'page_mode' => 'webinar_registration',
                    'webinar_room_url' => $webinarRoomUrl,
                    'funnel_optin_url' => $optinUrl,
                    'funnel_slug' => $pitchFunnel->slug,
                    'optin_funnel_slug' => $optinFunnel->slug,
                    'pitch_funnel_slug' => $pitchFunnel->slug,
                    'public_registration_url' => $squeezeUrl,
                    'editable' => true,
                ],
                'version' => 1,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>|null  $analysis
     */
    public function generateBonuses(Campaign $campaign, array $offer, ?array $analysis = null): void
    {
        $product = (string) ($offer['product_name'] ?? $campaign->name);

        $json = $this->openRouter->chatJson([
            [
                'role' => 'system',
                'content' => 'Create affiliate bonuses. Return ONLY JSON {"bonuses":[{"title":"","bonus_type":"ebook|checklist|mini_course|micro_app","content":"markdown body"}]} with 3 items tailored to the offer.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'offer' => $offer,
                    'analysis' => $analysis,
                ]),
            ],
        ]);

        $items = $json['ok'] ? ($json['data']['bonuses'] ?? []) : [];
        if (! is_array($items) || $items === []) {
            $items = [
                [
                    'title' => "{$product} Quick-Start Checklist",
                    'bonus_type' => 'checklist',
                    'content' => "## {$product} Checklist\n\n- Clarify your goal\n- Set up your account\n- Complete the first win in 15 minutes\n",
                ],
                [
                    'title' => "{$product} Mini Course Outline",
                    'bonus_type' => 'mini_course',
                    'content' => "## Module 1\nFoundations\n\n## Module 2\nImplementation\n\n## Module 3\nScale\n",
                ],
                [
                    'title' => "How to Get Results with {$product}",
                    'bonus_type' => 'ebook',
                    'content' => "# How to Get Results with {$product}\n\nA short how-to guide for new buyers.\n",
                ],
            ];
        }

        CampaignBonus::query()->where('campaign_id', $campaign->id)->delete();

        foreach ($items as $item) {
            CampaignBonus::query()->create([
                'campaign_id' => $campaign->id,
                'user_id' => $campaign->user_id,
                'title' => (string) ($item['title'] ?? 'Bonus'),
                'bonus_type' => (string) ($item['bonus_type'] ?? 'ebook'),
                'content' => (string) ($item['content'] ?? ''),
                'status' => 'ready',
            ]);
        }

        $bonusPage = $campaign->pages()->where('page_type', 'bonus')->first();
        if ($bonusPage) {
            $content = $bonusPage->content ?? [];
            $content['bonuses'] = $campaign->bonuses()->get(['title', 'bonus_type'])->toArray();
            $bonusPage->update(['content' => $content]);
        }
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>|null  $analysis
     */
    public function generateEmails(Campaign $campaign, array $offer, ?array $analysis = null, string $sequence = 'full_launch'): void
    {
        $product = (string) ($offer['product_name'] ?? $campaign->name);

        $json = $this->openRouter->chatJson([
            [
                'role' => 'system',
                'content' => 'Write affiliate promo email swipes. Return ONLY JSON {"emails":[{"sequence_key":"","subject":"","body":""}]} with 5-7 emails for sequence type '.$sequence.'.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'product' => $product,
                    'offer' => $offer,
                    'analysis' => $analysis,
                    'affiliate_link' => $campaign->affiliate_link,
                ]),
            ],
        ]);

        $emails = $json['ok'] ? ($json['data']['emails'] ?? []) : [];
        if (! is_array($emails) || $emails === []) {
            $emails = [
                ['sequence_key' => 'teaser', 'subject' => "Something big is coming for {$product}…", 'body' => "Hey,\n\nI've been quiet because I've been testing something related to {$product}.\n\nMore soon.\n"],
                ['sequence_key' => 'value', 'subject' => "The shortcut people use with {$product}", 'body' => "Quick tip before the full breakdown drops tomorrow.\n"],
                ['sequence_key' => 'launch', 'subject' => "It's live: {$product}", 'body' => 'The offer is open. Grab it here: '.$campaign->affiliate_link."\n"],
                ['sequence_key' => 'social_proof', 'subject' => 'What early users are saying', 'body' => "Here's what people noticed in the first 24 hours…\n"],
                ['sequence_key' => 'urgency', 'subject' => 'Last chance tonight', 'body' => 'Cart closes soon. Link: '.$campaign->affiliate_link."\n"],
            ];
        }

        CampaignEmail::query()->where('campaign_id', $campaign->id)->delete();

        foreach (array_values($emails) as $i => $email) {
            CampaignEmail::query()->create([
                'campaign_id' => $campaign->id,
                'sequence_key' => (string) ($email['sequence_key'] ?? 'email_'.($i + 1)),
                'subject' => (string) ($email['subject'] ?? 'Update'),
                'body' => (string) ($email['body'] ?? ''),
                'sort_order' => $i,
                'meta' => ['sequence' => $sequence],
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

    /**
     * @return array<string, mixed>
     */
    protected function defaultOptinSchema(): array
    {
        $html = <<<'HTML'
<section style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(160deg,#0f172a,#1e1b4b);padding:40px 20px;font-family:system-ui,sans-serif;color:#fff;">
  <div style="max-width:640px;width:100%;text-align:center;">
    <h1 style="font-size:2.25rem;line-height:1.2;margin:0 0 12px;">{{HEADLINE}}</h1>
    <p style="opacity:.85;font-size:1.1rem;margin:0 0 28px;">{{SUBHEADLINE}}</p>
    <form data-optin-form style="display:flex;flex-direction:column;gap:10px;max-width:420px;margin:0 auto;">
      <input name="name" placeholder="Your name" style="padding:14px 16px;border-radius:10px;border:0;" />
      <input name="email" type="email" required placeholder="Email address" style="padding:14px 16px;border-radius:10px;border:0;" />
      <button type="submit" style="padding:14px 16px;border-radius:10px;border:0;background:#6366f1;color:#fff;font-weight:700;cursor:pointer;">Reserve My Spot</button>
    </form>
    <p style="margin-top:16px;font-size:12px;opacity:.6;">{{PRODUCT}}</p>
  </div>
</section>
HTML;

        return [
            'html' => $html,
            'css' => '',
            'builder' => 'grapesjs',
        ];
    }
}
