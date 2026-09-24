<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignIntegration extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'campaign_id',
        'integration_account_id',
        'provider_list_config',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'provider_list_config' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function integrationAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationAccount::class);
    }
}
