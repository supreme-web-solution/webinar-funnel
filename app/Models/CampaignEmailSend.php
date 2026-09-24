<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignEmailSend extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'campaign_id',
        'campaign_email_id',
        'campaign_lead_id',
        'status',
        'scheduled_at',
        'sent_at',
        'error_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignEmail(): BelongsTo
    {
        return $this->belongsTo(CampaignEmail::class);
    }

    public function campaignLead(): BelongsTo
    {
        return $this->belongsTo(CampaignLead::class);
    }
}
