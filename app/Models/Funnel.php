<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Funnel extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'campaign_id',
        'template_id',
        'name',
        'slug',
        'status',
        'published_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'published_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(FunnelPage::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(FunnelSetting::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(FunnelIntegration::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function chatRoom(): HasOne
    {
        return $this->hasOne(ChatRoom::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function videoViewStats(): HasMany
    {
        return $this->hasMany(FunnelVideoViewStat::class);
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(FunnelPageView::class);
    }

    public function aiSources(): HasMany
    {
        return $this->hasMany(FunnelAiSource::class);
    }

    public function promotionPosts(): HasMany
    {
        return $this->hasMany(FunnelPromotionPost::class);
    }

    public function promotionTopicSuggestions(): HasMany
    {
        return $this->hasMany(FunnelPromotionTopicSuggestion::class);
    }

    public function usesCampaignSqueezeOptin(): bool
    {
        if (! $this->campaign_id) {
            return false;
        }

        $variant = (string) ($this->meta['campaign_variant'] ?? '');
        if (in_array($variant, ['optin_capture', 'webinar_optin', 'webinar_pitch'], true)) {
            return false;
        }

        if (! $this->relationLoaded('campaign')) {
            $this->load('campaign:id,slug,type');
        }

        if (! $this->campaign) {
            return false;
        }

        return $this->campaign->type === Campaign::TYPE_WEBINAR && $variant === 'webinar';
    }

    public function publicOptinUrl(): ?string
    {
        $username = $this->relationLoaded('user')
            ? $this->user?->username
            : $this->user()->value('username');

        if (! is_string($username) || $username === '' || ! is_string($this->slug) || $this->slug === '') {
            return null;
        }

        if ($this->usesCampaignSqueezeOptin()) {
            if (! $this->relationLoaded('campaign')) {
                $this->load('campaign:id,slug,type');
            }

            if ($this->campaign) {
                return route('public.campaign.page', [
                    'username' => $username,
                    'slug' => $this->campaign->slug,
                    'page' => 'squeeze',
                ]);
            }
        }

        return route('public.optin', [
            'username' => $username,
            'slug' => $this->slug,
        ]);
    }

    /**
     * @return array{optin: string, webinar: string, optin_from_campaign_squeeze: bool}
     */
    public function publicLinksForUsername(string $username): array
    {
        return [
            'optin' => $this->publicOptinUrl() ?? route('public.optin', [
                'username' => $username,
                'slug' => $this->slug,
            ]),
            'webinar' => route('public.webinar', [
                'username' => $username,
                'slug' => $this->slug,
            ]),
            'optin_from_campaign_squeeze' => $this->usesCampaignSqueezeOptin(),
        ];
    }
}
