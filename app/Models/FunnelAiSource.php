<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FunnelAiSource extends Model
{
    use HasPublicUuid;

    public const TYPE_URL = 'url';
    public const TYPE_TEXT = 'text';
    public const TYPE_FILE = 'file';

    protected $fillable = [
        'uuid',
        'funnel_id',
        'type',
        'title',
        'source_url',
        'status',
        'error_message',
        'content',
        'chunk_count',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'chunk_count' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function chunksRelation(): HasMany
    {
        return $this->hasMany(FunnelAiSourceChunk::class, 'funnel_ai_source_id');
    }

    /**
     * @return list<string>
     */
    public function chunks(int $size = 800): array
    {
        $raw = trim((string) $this->content);
        if ($raw === '') {
            return [];
        }

        $normalized = preg_replace('/\s+/', ' ', $raw) ?? $raw;
        $words = preg_split('/\s+/', trim($normalized)) ?: [];

        $chunks = [];
        $buffer = '';
        foreach ($words as $word) {
            $candidate = $buffer === '' ? $word : "{$buffer} {$word}";
            if (Str::length($candidate) > $size && $buffer !== '') {
                $chunks[] = trim($buffer);
                $buffer = $word;
            } else {
                $buffer = $candidate;
            }
        }

        if (trim($buffer) !== '') {
            $chunks[] = trim($buffer);
        }

        return array_values(array_filter($chunks, fn (string $chunk): bool => $chunk !== ''));
    }
}

