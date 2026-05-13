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
        'youtube_actor_id' => env('APIFY_YOUTUBE_ACTOR_ID', 'bernardo/youtube-scraper'),
        // News actor
        'news_enabled' => env('APIFY_NEWS_ENABLED', true),
        'news_actor_id' => env('APIFY_NEWS_ACTOR_ID', 'easyapi/google-news-scraper'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
    ],

    // Mentions ingestion (Twitter search).
    'twitter' => [
        'bearer_token' => env('TWITTER_BEARER_TOKEN'),
        'enabled' => env('TWITTER_ENABLED', true),
        'max_results' => env('TWITTER_MAX_RESULTS', 10),
        'cooldown_seconds' => env('TWITTER_COOLDOWN_SECONDS', 900),
    ],

    // Reddit OAuth for posting.
    'reddit' => [
        'client_id' => env('REDDIT_CLIENT_ID'),
        'client_secret' => env('REDDIT_CLIENT_SECRET'),
        'redirect' => env(
            'REDDIT_REDIRECT_URI',
            env('APP_URL', 'http://localhost').'/settings/social-traffic/reddit/callback'
        ),
        'scopes' => ['identity', 'read', 'submit'],
        'platform' => env('REDDIT_PLATFORM', 'web'),
        'app_id' => env('REDDIT_APP_ID', env('APP_NAME', 'laravel')),
        'version_string' => env('REDDIT_VERSION_STRING', '1.0'),
        'bot_username' => env('REDDIT_BOT_USERNAME', 'webbrain001'),
    ],

    // YouTube OAuth (Google).
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env(
            'GOOGLE_REDIRECT_URI',
            env('APP_URL', 'http://localhost').'/settings/social-traffic/youtube/callback'
        ),
        'scopes' => explode(' ', env(
            'GOOGLE_YOUTUBE_OAUTH_SCOPES',
            'https://www.googleapis.com/auth/youtube.force-ssl https://www.googleapis.com/auth/userinfo.email'
        )),
    ],

    // X OAuth 2.0 (PKCE).
    'x' => [
        'client_id' => env('X_CLIENT_ID'),
        'client_secret' => env('X_CLIENT_SECRET'),
        'redirect' => env(
            'X_REDIRECT_URI',
            env('APP_URL', 'http://localhost').'/settings/social-traffic/x/callback'
        ),
        'scopes' => explode(' ', env(
            'X_OAUTH_SCOPES',
            'tweet.read tweet.write users.read offline.access'
        )),
        'authorization_endpoint' => env('X_AUTHORIZATION_ENDPOINT', 'https://twitter.com/i/oauth2/authorize'),
        'token_endpoint' => env('X_TOKEN_ENDPOINT', 'https://api.twitter.com/2/oauth2/token'),
        'me_endpoint' => env('X_ME_ENDPOINT', 'https://api.twitter.com/2/users/me'),
    ],
];
