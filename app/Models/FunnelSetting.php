<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelSetting extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'funnel_id',
        'webinar_title',
        'webinar_description',
        'video_url',
        'webinar_duration_seconds',
        'webinar_cta_label',
        'webinar_cta_url',
        'affiliate_request_link',
        'jv_page',
        'chat_mode',
        'countdown_seconds',
        'allow_replay',
        'chat_seed_messages',
        'branding',
        'offers',
        'exit_popup_enabled',
        'exit_popup_show_close',
        'exit_popup_title',
        'exit_popup_description',
        'exit_popup_cta_label',
        'exit_popup_cta_url',
        'traffic_ai_reply_enabled',
        'traffic_ai_link_override',
        'traffic_ai_extra_context',
        'traffic_ai_max_replies_per_day',
        'traffic_ai_social_account_ids',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'webinar_duration_seconds' => 'integer',
            'allow_replay' => 'boolean',
            'chat_seed_messages' => 'array',
            'branding' => 'array',
            'offers' => 'array',
            'exit_popup_enabled' => 'boolean',
            'exit_popup_show_close' => 'boolean',
            'traffic_ai_reply_enabled' => 'boolean',
            'traffic_ai_max_replies_per_day' => 'integer',
            'traffic_ai_social_account_ids' => 'array',
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /**
     * Link embedded in AI replies: override, then affiliate request, then first enabled offer, then webinar CTA.
     */
    public function effectiveTrafficAffiliateLink(): ?string
    {
        $override = trim((string) $this->traffic_ai_link_override);
        if ($override !== '') {
            return $override;
        }

        $aff = trim((string) $this->affiliate_request_link);
        if ($aff !== '') {
            return $aff;
        }

        foreach ($this->offers ?? [] as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            if (array_key_exists('enabled', $offer) && $offer['enabled'] === false) {
                continue;
            }
            $url = trim((string) ($offer['cta_url'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        $cta = trim((string) $this->webinar_cta_url);

        return $cta !== '' ? $cta : null;
    }
}
