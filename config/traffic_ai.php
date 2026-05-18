<?php

return [

    'enabled' => env('TRAFFIC_AI_ENABLED', true),

    /**
     * Max auto-replies per connected social account per calendar day (all funnels combined).
     */
    'max_replies_per_day_per_account' => (int) env('TRAFFIC_AI_MAX_REPLIES_PER_DAY', 20),

    /**
     * When false, the observer still loads funnel settings but skips dispatch if disabled.
     */
    'dispatch_on_mention_created' => env('TRAFFIC_AI_DISPATCH_ON_MENTION', true),

    'queues' => [
        'evaluate' => env('TRAFFIC_AI_QUEUE_EVALUATE', 'traffic-evaluate'),
        'generate' => env('TRAFFIC_AI_QUEUE_GENERATE', 'traffic-generate'),
        'post' => env('TRAFFIC_AI_QUEUE_POST', 'traffic-post'),
    ],

    /**
     * Minimum seconds between successful posts for the same social account (global).
     * Jitter is added in SocialAccountPostingLimiter to desynchronize workers.
     */
    'min_seconds_between_posts' => (int) env('TRAFFIC_AI_MIN_SECONDS_BETWEEN_POSTS', 120),

    'post_jitter_seconds' => [
        'min' => (int) env('TRAFFIC_AI_POST_JITTER_MIN', 30),
        'max' => (int) env('TRAFFIC_AI_POST_JITTER_MAX', 90),
    ],

    /**
     * Max delayed re-dispatches of the post job for spacing / rate limits (not HTTP retries).
     */
    'max_post_dispatches' => (int) env('TRAFFIC_AI_MAX_POST_DISPATCHES', 50),

    'openai' => [
        'timeout' => (int) env('TRAFFIC_AI_OPENAI_TIMEOUT', 45),
        'max_reply_chars' => [
            'reddit' => 8000,
            'youtube' => 4000,
            'twitter' => 260,
            'default' => 2000,
        ],
    ],
];
