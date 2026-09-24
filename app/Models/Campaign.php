<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Services\Funnels\PublicFunnelResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasPublicUuid;

    public const TYPE_SALES = 'sales';

    public const TYPE_WEBINAR = 'webinar';

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'slug',
        'type',
        'status',
        'wizard_step',
        'offer_url',
        'affiliate_link',
        'marketplace',
        'offer_data',
        'analysis',
        'knowledge',
        'published_at',
        'meta',
        'generation_state',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'wizard_step' => 'integer',
            'offer_data' => 'array',
            'analysis' => 'array',
            'knowledge' => 'array',
            'published_at' => 'datetime',
            'meta' => 'array',
            'generation_state' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CampaignPage::class);
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(CampaignBonus::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(CampaignEmail::class);
    }

    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class);
    }

    public function trackedLinks(): HasMany
    {
        return $this->hasMany(TrackedLink::class);
    }

    public function customDomains(): HasMany
    {
        return $this->hasMany(CustomDomain::class);
    }

    public function campaignLeads(): HasMany
    {
        return $this->hasMany(CampaignLead::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(CampaignIntegration::class);
    }

    public function emailSends(): HasMany
    {
        return $this->hasMany(CampaignEmailSend::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Campaign $campaign): void {
            $campaign->trackedLinks()->delete();

            if ($campaign->type !== self::TYPE_WEBINAR) {
                return;
            }

            $campaign->loadMissing('user');
            $username = $campaign->user?->username ?? 'user-'.$campaign->user_id;
            $resolver = app(PublicFunnelResolver::class);

            $campaign->funnels()->each(function (Funnel $funnel) use ($resolver, $username): void {
                $slug = $funnel->slug;
                $funnel->delete();
                $resolver->forget($username, $slug);
            });
        });
    }
}
