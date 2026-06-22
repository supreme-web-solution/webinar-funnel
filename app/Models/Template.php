<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'category',
        'conversion_style',
        'thumbnail_url',
        'paid_traffic_drive_url',
        'default_palette',
        'suggested_keywords',
        'vendor_contact',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'default_palette' => 'array',
            'suggested_keywords' => 'array',
            'vendor_contact' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }

    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class);
    }
}
