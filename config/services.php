<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'apify' => [
        'api_token' => env('APIFY_API_TOKEN'),
        'enabled' => env('APIFY_ENABLED', true),
        'max_items_per_search' => env('APIFY_MAX_ITEMS_PER_SEARCH', 25),
        'default_sort' => env('APIFY_DEFAULT_SORT', 'top'),
        // Reddit actor
        'actor_id' => env('APIFY_ACTOR_ID', 'practicaltools/apify-reddit-api'),
        // YouTube actor
        'youtube_enabled' => env('APIFY_YOUTUBE_ENABLED', true),
        'youtube_actor_id' => env('APIFY_YOUTUBE_ACTOR_ID', 'streamers/youtube-scraper'),
        'youtube_timeout' => env('APIFY_YOUTUBE_TIMEOUT', 300),
        // News actor
        'news_enabled' => env('APIFY_NEWS_ENABLED', true),
        'news_actor_id' => env('APIFY_NEWS_ACTOR_ID', 'easyapi/google-news-scraper'),
        'news_max_items' => env('APIFY_NEWS_MAX_ITEMS', 100),
        'news_language' => env('APIFY_NEWS_LANGUAGE', 'lang_en'),
        'news_country' => env('APIFY_NEWS_COUNTRY', 'US'),
        'news_timeout' => env('APIFY_NEWS_TIMEOUT', 240),
        // Twitter/X global keyword search (cookieless; no Zernio account needed for discovery)
        'twitter_enabled' => env('APIFY_TWITTER_ENABLED', true),
        'twitter_actor_id' => env('APIFY_TWITTER_ACTOR_ID', 'patient_discovery/twitter-search'),
        'twitter_timeout' => env('APIFY_TWITTER_TIMEOUT', 300),
        'twitter_cooldown_seconds' => env('APIFY_TWITTER_COOLDOWN_SECONDS', 900),
        'twitter_exclude_retweets' => env('APIFY_TWITTER_EXCLUDE_RETWEETS', true),
        'twitter_lang' => env('APIFY_TWITTER_LANG', 'en'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],

    'scrapingbee' => [
        'api_key' => env('SCRAPINGBEE_API_KEY'),
    ],

    // Zernio – OAuth, inbox (Twitter/X), and reply posting for traffic auto-replies.
    'zernio' => [
        'api_key' => env('ZERNIO_API_KEY'),
        'enabled' => env('ZERNIO_ENABLED', true),
        'base_url' => env('ZERNIO_BASE_URL', 'https://zernio.com/api'),
        'timeout' => env('ZERNIO_TIMEOUT', 60),
    ],
];
