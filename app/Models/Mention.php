<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mention extends Model
{
    protected $fillable = [
        'keyword_id',
        'user_id',
        'post_id',
        'title',
        'content',
        'source',
        'source_type',
        'author_id',
        'username',
        'like_count',
        'retweet_count',
        'comments_count',
        'favourite_count',
        'views',
        'votes',
        'category',
        'status',
        'permalink',
        'posted_at',
    ];

    protected $casts = [
        'like_count' => 'integer',
        'retweet_count' => 'integer',
        'comments_count' => 'integer',
        'favourite_count' => 'integer',
        'views' => 'integer',
        'votes' => 'integer',
        'posted_at' => 'datetime',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trafficReplyAttempt(): HasOne
    {
        return $this->hasOne(TrafficReplyAttempt::class);
    }
}
