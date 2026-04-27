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
        'api_token'            => env('APIFY_API_TOKEN'),
        'enabled'              => env('APIFY_ENABLED', true),
        'max_items_per_search' => env('APIFY_MAX_ITEMS_PER_SEARCH', 25),
        'default_sort'         => env('APIFY_DEFAULT_SORT', 'top'),
        // Reddit actor
        'actor_id'             => env('APIFY_ACTOR_ID', 'practicaltools/apify-reddit-api'),
        // YouTube actor
        'youtube_enabled'      => env('APIFY_YOUTUBE_ENABLED', true),
        'youtube_actor_id'     => env('APIFY_YOUTUBE_ACTOR_ID', 'bernardo/youtube-scraper'),
        // News actor
        'news_enabled'         => env('APIFY_NEWS_ENABLED', true),
        'news_actor_id'        => env('APIFY_NEWS_ACTOR_ID', 'easyapi/google-news-scraper'),
    ],

    'twitter' => [
        'bearer_token'    => env('TWITTER_BEARER_TOKEN'),
        'enabled'         => env('TWITTER_ENABLED', true),
        'max_results'     => env('TWITTER_MAX_RESULTS', 10),
        'cooldown_seconds' => env('TWITTER_COOLDOWN_SECONDS', 900),
    ],

];
