<?php

namespace App\Models;

use App\Services\Ads\AdPlatformRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FunnelAdCampaign extends Model
{
    public const STATUS_DRAFT      = 'draft';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_READY      = 'ready';
    public const STATUS_LAUNCHING  = 'launching';
    public const STATUS_ACTIVE     = 'active';
    public const STATUS_PAUSED     = 'paused';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    public const GOAL_TRAFFIC         = 'traffic';
    public const GOAL_AWARENESS       = 'awareness';
    public const GOAL_ENGAGEMENT      = 'engagement';
    public const GOAL_LEAD_GENERATION = 'lead_generation';
    public const GOAL_CONVERSIONS     = 'conversions';

    public const GOALS = [
        self::GOAL_TRAFFIC         => 'Drive Traffic',
        self::GOAL_AWARENESS       => 'Brand Awareness',
        self::GOAL_ENGAGEMENT      => 'Engagement',
        self::GOAL_LEAD_GENERATION => 'Lead Generation',
        self::GOAL_CONVERSIONS     => 'Conversions / Sales',
    ];

    public const AD_PLATFORMS = [
        'facebook'  => ['label' => 'Facebook',  'icon' => 'simple-icons:facebook'],
        'instagram' => ['label' => 'Instagram', 'icon' => 'simple-icons:instagram'],
        'tiktok'    => ['label' => 'TikTok',    'icon' => 'simple-icons:tiktok'],
        'google'    => ['label' => 'Google',    'icon' => 'simple-icons:google'],
        'x'         => ['label' => 'X / Twitter', 'icon' => 'simple-icons:x'],
        'linkedin'  => ['label' => 'LinkedIn',  'icon' => 'simple-icons:linkedin'],
        'reddit'    => ['label' => 'Reddit',    'icon' => 'simple-icons:reddit'],
        'pinterest' => ['label' => 'Pinterest', 'icon' => 'simple-icons:pinterest'],
        'youtube'   => ['label' => 'YouTube',   'icon' => 'simple-icons:youtube'],
    ];

    protected $fillable = [
        'funnel_id', 'user_id', 'name', 'goal', 'platforms', 'status',
        'product_url', 'industry', 'goal_description', 'ai_research',
        'budget_amount', 'budget_type', 'budget_currency', 'start_date', 'end_date',
        'targeting', 'platform_ad_account_ids', 'zernio_social_account_id', 'zernio_ad_account_id', 'zernio_campaign_id',
        'meta_pixel_id', 'meta_conversion_event',
        'performance', 'last_synced_at', 'last_error', 'launch_errors',
    ];

    protected function casts(): array
    {
        return [
            'platforms'      => 'array',
            'ai_research'    => 'array',
            'targeting'      => 'array',
            'platform_ad_account_ids' => 'array',
            'performance'    => 'array',
            'launch_errors'  => 'array',
            'budget_amount'  => 'decimal:2',
            'start_date'     => 'date',
            'end_date'       => 'date',
            'last_synced_at' => 'datetime',
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(FunnelAdCreative::class, 'campaign_id');
    }

    public function isLive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_LAUNCHING], true);
    }

    /**
     * @return array<string, string>
     */
    public static function launchableAdPlatforms(): array
    {
        return array_intersect_key(
            self::AD_PLATFORMS,
            array_flip(AdPlatformRules::ZERNIO_CREATE_PLATFORMS)
        );
    }

    /**
     * @return array<string, string>
     */
    public static function unsupportedAdPlatforms(): array
    {
        return array_intersect_key(
            self::AD_PLATFORMS,
            array_flip(AdPlatformRules::NOT_STANDALONE_CREATE)
        );
    }
}
