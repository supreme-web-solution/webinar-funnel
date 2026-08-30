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
        // Reddit search: sort=new + time=year keeps results recent (Apify actor practicaltools/apify-reddit-api).
        // Allowed time values: hour, day, week, month, year, all (Reddit has no 90-day bucket).
        'default_sort' => env('APIFY_DEFAULT_SORT', 'new'),
        'default_time' => env('APIFY_DEFAULT_TIME', 'year'),
        // Safety cap after Apify returns (exact 90d is not available in the actor payload).
        'max_post_age_days' => (int) env('APIFY_MAX_POST_AGE_DAYS', 90),
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

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'timeout' => env('OPENROUTER_TIMEOUT', 90),
        'connect_timeout' => env('OPENROUTER_CONNECT_TIMEOUT', 15),
        'retries' => env('OPENROUTER_RETRIES', 2),
        'lead_magnet_timeout' => env('OPENROUTER_LEAD_MAGNET_TIMEOUT', 180),
        'lead_magnet_max_tokens' => env('OPENROUTER_LEAD_MAGNET_MAX_TOKENS', 8192),
        'models' => [
            'lead_magnet_blueprint' => env('OPENROUTER_MODEL_LM_BLUEPRINT', env('OPENROUTER_MODEL', 'openai/gpt-4o-mini')),
            'lead_magnet_content' => env('OPENROUTER_MODEL_LM_CONTENT', env('OPENROUTER_MODEL', 'openai/gpt-4o')),
            'lead_magnet_polish' => env('OPENROUTER_MODEL_LM_POLISH', env('OPENROUTER_MODEL', 'openai/gpt-4o')),
            'free_fallback' => env('OPENROUTER_MODEL_FREE', 'google/gemma-2-9b-it:free'),
        ],
    ],

    'scrapingbee' => [
        'api_key' => env('SCRAPINGBEE_API_KEY'),
    ],

    // Free URL-to-markdown reader (no key required; optional JINA_API_KEY for higher limits)
    'jina_reader' => [
        'enabled' => env('JINA_READER_ENABLED', true),
        'api_key' => env('JINA_API_KEY'),
        'timeout' => env('JINA_READER_TIMEOUT', 60),
    ],

    'sales_page_fetcher' => [
        'direct_timeout' => env('SALES_PAGE_DIRECT_TIMEOUT', 30),
    ],

    'campaigns' => [
        'generation_queue' => env('CAMPAIGN_GENERATION_QUEUE', 'campaign-generate'),
    ],

    /*
    | Marketplace offer search (Opportunity Finder + campaign keyword intake).
    | JVZoo / WarriorPlus: public pages scraped via Jina Reader (free).
    | ClickBank: React SPA — needs browser automation (Apify actor types search + reads XHR).
    | URL ?search= params are ignored by ClickBank; plain HTTP/Jina returns an empty shell.
    */
    'marketplace' => [
        'max_results' => (int) env('MARKETPLACE_MAX_RESULTS', 12),
        'html_scrape_enabled' => env('MARKETPLACE_HTML_SCRAPE_ENABLED', true),
        'html_timeout' => (int) env('MARKETPLACE_HTML_TIMEOUT', 30),
        'jvzoo_html_enabled' => env('MARKETPLACE_JVZOO_HTML_ENABLED', true),
        'warriorplus_html_enabled' => env('MARKETPLACE_WARRIORPLUS_HTML_ENABLED', true),
        'clickbank_apify_enabled' => env('MARKETPLACE_CLICKBANK_APIFY_ENABLED', true),
        'clickbank_apify_actor_id' => env('MARKETPLACE_CLICKBANK_APIFY_ACTOR_ID', 'bovi/clickbank-marketplace-scraper'),
        'clickbank_apify_timeout' => (int) env('MARKETPLACE_CLICKBANK_APIFY_TIMEOUT', 180),
        'clickbank_affiliate_nickname' => env('CLICKBANK_AFFILIATE_NICKNAME'),
        'trending_keywords' => array_filter(array_map('trim', explode(',', (string) env(
            'MARKETPLACE_TRENDING_KEYWORDS',
            'ai software,weight loss,email marketing,keto'
        )))),
        'trending_cache_ttl' => (int) env('MARKETPLACE_TRENDING_CACHE_TTL', 86400),
    ],

    'did' => [
        'api_key' => env('DID_API_KEY'),
        'enabled' => env('DID_ENABLED', false),
        'timeout' => (int) env('DID_TIMEOUT', 120),
        'default_voice_id' => env('DID_DEFAULT_VOICE_ID', 'en-US-JennyNeural'),
        'default_presenter_url' => env('DID_DEFAULT_PRESENTER_URL', ''),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'url' => env('CLOUDINARY_URL'),
    ],

    // Zernio – OAuth, inbox (Twitter/X), and reply posting for traffic auto-replies.
    'zernio' => [
        'api_key' => env('ZERNIO_API_KEY'),
        'enabled' => env('ZERNIO_ENABLED', true),
        'base_url' => env('ZERNIO_BASE_URL', 'https://zernio.com/api'),
        'timeout' => env('ZERNIO_TIMEOUT', 60),
        'default_social_account_id' => env('ZERNIO_DEFAULT_SOCIAL_ACCOUNT_ID'),
    ],
];
