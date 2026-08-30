<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignBonus;
use App\Models\CampaignLead;
use App\Models\CampaignPage;
use App\Models\User;
use App\Services\Campaigns\CampaignBonusPresenterService;
use App\Services\Campaigns\CampaignLeadCaptureService;
use App\Services\Campaigns\CampaignLinkResolverService;
use App\Services\Campaigns\LeadMagnetPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicCampaignController extends Controller
{
    public function __construct(
        protected CampaignLinkResolverService $linkResolver,
        protected CampaignBonusPresenterService $bonusPresenter,
        protected LeadMagnetPdfService $pdf,
        protected CampaignLeadCaptureService $leadCapture,
    ) {}

    public function page(Request $request, string $username, string $slug, string $page): Response|RedirectResponse
    {
        $user = User::query()->where('username', $username)->firstOrFail();

        $campaign = Campaign::query()
            ->where('user_id', $user->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        abort_unless(in_array($page, ['squeeze', 'thankyou', 'quiz', 'bonus'], true), 404);

        $campaignPage = CampaignPage::query()
            ->where('campaign_id', $campaign->id)
            ->where('page_type', $page)
            ->first();

        $content = $campaignPage?->content ?? [];

        $affiliateUrl = $this->linkResolver->affiliatePublicUrl($campaign);

        if ($page === 'thankyou') {
            $token = $request->query('dl');
            if (is_string($token) && $token !== '') {
                $lead = CampaignLead::query()->where('download_token', $token)->where('campaign_id', $campaign->id)->first();
                if ($lead) {
                    $content['download_url'] = route('campaigns.lead-magnet.download', ['token' => $token]);
                }
            }
            if ($affiliateUrl) {
                $content['bridge_url'] = $affiliateUrl;
            }
        }

        if ($page === 'bonus') {
            $content['bonuses'] = $this->bonusPresenter->featuredForPage($campaign, $content, $username);
            $content['total_value_label'] = $this->totalValueLabel($content['bonuses']);
            if ($affiliateUrl) {
                $content['affiliate_url'] = $affiliateUrl;
            }
        }

        return Inertia::render('public/campaign/'.$this->pageComponent($page), [
            'campaign' => [
                'name' => $campaign->name,
                'type' => $campaign->type,
                'uuid' => $campaign->uuid,
            ],
            'content' => $content,
            'username' => $username,
            'slug' => $slug,
            'optin_url' => route('public.campaign.optin', ['username' => $username, 'slug' => $slug]),
        ]);
    }

    public function bonusViewer(string $username, string $slug, string $bonusUuid): Response
    {
        [$campaign, $bonus] = $this->resolveBonus($username, $slug, $bonusUuid);

        return Inertia::render('public/campaign/BonusViewer', [
            'campaign' => [
                'name' => $campaign->name,
                'uuid' => $campaign->uuid,
            ],
            'bonus' => $this->bonusPresenter->viewerPayload($campaign, $bonus, $username),
            'username' => $username,
            'slug' => $slug,
        ]);
    }

    public function bonusDownload(string $username, string $slug, string $bonusUuid): StreamedResponse
    {
        [$campaign, $bonus] = $this->resolveBonus($username, $slug, $bonusUuid);

        abort_unless($bonus->bonus_type === 'ebook', 404);

        $meta = is_array($bonus->meta) ? $bonus->meta : [];
        $pdfPath = $meta['pdf_path'] ?? null;
        $htmlPath = $meta['download_path'] ?? null;

        if (is_string($htmlPath) && $htmlPath !== '') {
            $pdfPath = $this->pdf->ensurePdf($htmlPath, is_string($pdfPath) ? $pdfPath : null);
            if ($pdfPath && $pdfPath !== ($meta['pdf_path'] ?? null)) {
                $meta['pdf_path'] = $pdfPath;
                $bonus->update(['meta' => $meta]);
            }
        }

        abort_unless(is_string($pdfPath) && Storage::disk('public')->exists($pdfPath), 404);

        $filename = Str::slug($bonus->title).'.pdf';

        return Storage::disk('public')->download($pdfPath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @return array{0: Campaign, 1: \App\Models\CampaignBonus}
     */
    protected function resolveBonus(string $username, string $slug, string $bonusUuid): array
    {
        $user = User::query()->where('username', $username)->firstOrFail();

        $campaign = Campaign::query()
            ->where('user_id', $user->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $bonus = CampaignBonus::query()
            ->where('user_id', $user->id)
            ->where('uuid', $bonusUuid)
            ->where('status', 'ready')
            ->firstOrFail();

        if ((int) $bonus->campaign_id !== (int) $campaign->id) {
            $bonusPage = CampaignPage::query()
                ->where('campaign_id', $campaign->id)
                ->where('page_type', 'bonus')
                ->first();
            $featured = is_array($bonusPage?->content['featured_bonus_uuids'] ?? null)
                ? $bonusPage->content['featured_bonus_uuids']
                : [];

            if (! in_array($bonus->uuid, $featured, true)) {
                abort(404);
            }
        }

        return [$campaign, $bonus];
    }

    /**
     * @param  list<array<string, mixed>>  $bonuses
     */
    protected function totalValueLabel(array $bonuses): string
    {
        $total = 0;
        foreach ($bonuses as $bonus) {
            if (preg_match('/\$(\d+)/', (string) ($bonus['value_label'] ?? ''), $m)) {
                $total += (int) $m[1];
            }
        }

        if ($total <= 0) {
            $count = count($bonuses);

            return $count.' bonus'.($count === 1 ? '' : 'es');
        }

        return count($bonuses).' bonuses · $'.$total.' total value';
    }

    public function optin(Request $request, string $username, string $slug): RedirectResponse
    {
        $user = User::query()->where('username', $username)->firstOrFail();

        $campaign = Campaign::query()
            ->where('user_id', $user->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $hash = hash('sha256', strtolower($validated['email']));
        $token = Str::random(48);

        CampaignLead::query()->updateOrCreate(
            ['campaign_id' => $campaign->id, 'email_hash' => $hash],
            [
                'name' => $validated['name'] ?? '',
                'email' => $validated['email'],
                'download_token' => $token,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]
        );

        $this->leadCapture->capture($campaign, $validated, $request);

        if ($campaign->type === Campaign::TYPE_WEBINAR) {
            $squeeze = CampaignPage::query()
                ->where('campaign_id', $campaign->id)
                ->where('page_type', 'squeeze')
                ->first();

            $roomUrl = is_array($squeeze?->content)
                ? ($squeeze->content['webinar_room_url'] ?? null)
                : null;

            if (! is_string($roomUrl) || $roomUrl === '') {
                $campaign->loadMissing('funnels');
                $meta = $campaign->meta ?? [];
                $primaryId = $meta['primary_webinar_funnel_id'] ?? null;
                $funnel = $primaryId
                    ? $campaign->funnels->firstWhere('id', $primaryId)
                    : $campaign->funnels->first();

                if ($funnel) {
                    $roomUrl = route('public.webinar', ['username' => $username, 'slug' => $funnel->slug]);
                }
            }

            if (is_string($roomUrl) && $roomUrl !== '') {
                return redirect()->away($roomUrl);
            }
        }

        return redirect()->route('public.campaign.page', [
            'username' => $username,
            'slug' => $slug,
            'page' => 'thankyou',
            'dl' => $token,
        ]);
    }

    protected function pageComponent(string $page): string
    {
        return match ($page) {
            'thankyou' => 'ThankYou',
            'bonus' => 'Bonus',
            'quiz' => 'Quiz',
            default => 'Squeeze',
        };
    }
}
