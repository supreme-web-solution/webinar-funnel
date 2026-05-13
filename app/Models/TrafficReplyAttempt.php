<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficReplyAttempt extends Model
{
    public const STATUS_PENDING_EVALUATION = 'pending_evaluation';

    public const STATUS_SKIPPED_DISABLED = 'skipped_disabled';

    public const STATUS_SKIPPED_NO_ACCOUNT = 'skipped_no_account';

    public const STATUS_SKIPPED_UNSUPPORTED = 'skipped_unsupported';

    public const STATUS_SKIPPED_DAILY_CAP = 'skipped_daily_cap';

    public const STATUS_SKIPPED_GATE = 'skipped_gate';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_QUEUED_POST = 'queued_post';

    public const STATUS_POSTED = 'posted';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'mention_id',
        'funnel_id',
        'user_id',
        'social_account_id',
        'status',
        'skip_reason',
        'gate_details',
        'reply_text',
        'external_comment_id',
        'last_error',
        'post_dispatch_count',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'gate_details' => 'array',
            'posted_at' => 'datetime',
        ];
    }

    public function mention(): BelongsTo
    {
        return $this->belongsTo(Mention::class);
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
