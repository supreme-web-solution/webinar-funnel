<?php

return [
    'enabled' => env('PROMOTION_ENABLED', true),

    'default_timezone' => env('PROMOTION_DEFAULT_TIMEZONE', 'UTC'),

    'default_sequence_size' => (int) env('PROMOTION_DEFAULT_SEQUENCE_SIZE', 12),

    'max_sequence_size' => (int) env('PROMOTION_MAX_SEQUENCE_SIZE', 30),

    'supported_platforms' => [
        'facebook',
        'instagram',
        'tiktok',
        'linkedin',
        'twitter',
        'youtube',
        'reddit',
        'threads',
        'pinterest',
    ],

    'queues' => [
        'generate' => env('PROMOTION_QUEUE_GENERATE', 'promotion-generate'),
        'publish' => env('PROMOTION_QUEUE_PUBLISH', 'promotion-publish'),
    ],

    'carousel' => [
        /** Hard cap for slide copy + rendered images (env: PROMOTION_CAROUSEL_MAX_SLIDES). */
        'max_slide_images' => (int) env('PROMOTION_CAROUSEL_MAX_SLIDES', 6),
        /** text_template = typography templates (default); ai_image = optional photo slides. */
        'render_mode' => env('PROMOTION_CAROUSEL_RENDER_MODE', 'text_template'),
    ],

    // Promotion AI runs through OpenRouter (same OpenAI models, unified billing/key).
    'openrouter' => [
        'text_model' => env(
            'PROMOTION_OPENROUTER_TEXT_MODEL',
            env('PROMOTION_OPENAI_TEXT_MODEL', env('OPENROUTER_MODEL', 'openai/gpt-4o-mini')),
        ),
        'image_model' => env(
            'PROMOTION_OPENROUTER_IMAGE_MODEL',
            env('PROMOTION_OPENAI_IMAGE_MODEL', 'openai/gpt-image-1'),
        ),
        'timeout' => (int) env('PROMOTION_OPENROUTER_TIMEOUT', env('PROMOTION_OPENAI_TIMEOUT', 90)),
    ],

    /** @deprecated Use promotion.openrouter — kept for backward-compatible env names */
    'openai' => [
        'text_model' => env(
            'PROMOTION_OPENAI_TEXT_MODEL',
            env('PROMOTION_OPENROUTER_TEXT_MODEL', env('OPENROUTER_MODEL', 'openai/gpt-4o-mini')),
        ),
        'image_model' => env(
            'PROMOTION_OPENAI_IMAGE_MODEL',
            env('PROMOTION_OPENROUTER_IMAGE_MODEL', 'openai/gpt-image-1'),
        ),
        'timeout' => (int) env('PROMOTION_OPENAI_TIMEOUT', env('PROMOTION_OPENROUTER_TIMEOUT', 90)),
    ],

    'ads' => [
        'enabled' => filter_var(env('PAID_ADS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        // Hard safety limit so image generation cannot exceed this per creative generation run.
        'max_generated_creatives' => (int) env('PROMOTION_ADS_MAX_GENERATED_CREATIVES', 5),
        'min_budget_amount' => (float) env('PROMOTION_ADS_MIN_BUDGET_AMOUNT', 2),
        'default_budget_currency' => env('PROMOTION_ADS_DEFAULT_BUDGET_CURRENCY', 'USD'),
        // Minimum daily budget per ad-account billing currency (sent as-is to Meta/Zernio).
        'min_budget_by_currency' => [
            'USD' => 2,
            'NGN' => 1762,
            'EUR' => 2,
            'GBP' => 2,
            'CAD' => 2,
            'AUD' => 2,
            'INR' => 100,
            'ZAR' => 50,
        ],
        'default_meta_pixel_id' => env('PROMOTION_ADS_DEFAULT_META_PIXEL_ID'),
        'default_meta_conversion_event' => env('PROMOTION_ADS_DEFAULT_META_CONVERSION_EVENT', 'LEAD'),
    ],

    'did' => [
        'enabled' => env('DID_ENABLED', false),
        'api_key' => env('DID_API_KEY'),
        'default_voice_id' => env('DID_DEFAULT_VOICE_ID', 'en-US-JennyNeural'),
        'default_presenter_url' => env('DID_DEFAULT_PRESENTER_URL', ''),
        'timeout' => (int) env('DID_TIMEOUT', 120),
        'poll_interval_seconds' => (int) env('DID_POLL_INTERVAL_SECONDS', 15),
        'poll_max_attempts' => (int) env('DID_POLL_MAX_ATTEMPTS', 60),
    ],

    'zernio' => [
        'default_publish_endpoint' => env('ZERNIO_POST_ENDPOINT', '/v1/posts'),
        'publish_poll_attempts' => (int) env('ZERNIO_PUBLISH_POLL_ATTEMPTS', 15),
        'publish_poll_interval_seconds' => (int) env('ZERNIO_PUBLISH_POLL_INTERVAL_SECONDS', 3),
        'thread_poll_attempts' => (int) env('ZERNIO_THREAD_POLL_ATTEMPTS', 20),
        'thread_poll_interval_seconds' => (int) env('ZERNIO_THREAD_POLL_INTERVAL_SECONDS', 2),
        /** chain = one API call per tweet with replyToTweetId; batch = single threadItems call. */
        'twitter_thread_publish_mode' => env('ZERNIO_TWITTER_THREAD_MODE', 'chain'),
        /** Delay between chained X thread replies (ms) to avoid rate limits and broken chains. */
        'twitter_thread_delay_ms' => (int) env('ZERNIO_TWITTER_THREAD_DELAY_MS', 3000),
    ],

    'platform_content_limits' => [
        'tiktok_photo' => 90,
        'tiktok_video' => 2200,
        'tiktok_photo_description' => 4000,
        'youtube_title' => 100,
        'twitter' => 280,
        'reddit_title' => 300,
        'reddit_body' => 4000,
    ],
];
