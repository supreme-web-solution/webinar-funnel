<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelAdCreative extends Model
{
    public const STATUS_DRAFT  = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_WINNER = 'winner';
    public const STATUS_LOSER  = 'loser';

    public const FORMAT_SQUARE    = 'square';
    public const FORMAT_STORY     = 'story';
    public const FORMAT_LANDSCAPE = 'landscape';
    public const FORMAT_REEL      = 'reel';

    public const CTA_BUTTONS = [
        'LEARN_MORE'    => 'Learn More',
        'SHOP_NOW'      => 'Shop Now',
        'SIGN_UP'       => 'Sign Up',
        'WATCH_NOW'     => 'Watch Now',
        'GET_OFFER'     => 'Get Offer',
        'DOWNLOAD'      => 'Download',
        'CONTACT_US'    => 'Contact Us',
        'BOOK_NOW'      => 'Book Now',
        'REGISTER'      => 'Register',
    ];

    protected $fillable = [
        'campaign_id', 'funnel_id', 'user_id',
        'headline', 'primary_text', 'description', 'cta_button',
        'asset_url', 'asset_type', 'format',
        'status', 'is_winner',
        'zernio_post_id', 'zernio_ad_id', 'platform_ad_ids',
        'performance',
    ];

    protected function casts(): array
    {
        return [
            'is_winner'   => 'boolean',
            'performance' => 'array',
            'platform_ad_ids' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FunnelAdCampaign::class, 'campaign_id');
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
