<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class TemplateVersion extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'template_id',
        'version',
        'optin_schema',
        'webinar_schema',
        'default_settings',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'optin_schema' => 'array',
            'webinar_schema' => 'array',
            'default_settings' => 'array',
            'is_current' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
