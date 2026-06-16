<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Funnel extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'user_id',
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
}
