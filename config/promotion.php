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
        'pinterest',
        'twitter',
        'youtube',
        'reddit',
    ],

    'queues' => [
        'generate' => env('PROMOTION_QUEUE_GENERATE', 'promotion-generate'),
        'publish' => env('PROMOTION_QUEUE_PUBLISH', 'promotion-publish'),
    ],

    'openai' => [
        'text_model'  => env('PROMOTION_OPENAI_TEXT_MODEL',  'gpt-4o-mini'),
        'image_model' => env('PROMOTION_OPENAI_IMAGE_MODEL', 'gpt-image-1'),
        'timeout'     => (int) env('PROMOTION_OPENAI_TIMEOUT', 90),
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
        'enabled'               => env('DID_ENABLED', false),
        'api_key'               => env('DID_API_KEY'),
        'default_voice_id'      => env('DID_DEFAULT_VOICE_ID', 'en-US-JennyNeural'),
        'default_presenter_url' => env('DID_DEFAULT_PRESENTER_URL', ''),
        'timeout'               => (int) env('DID_TIMEOUT', 120),
        'poll_interval_seconds' => (int) env('DID_POLL_INTERVAL_SECONDS', 15),
        'poll_max_attempts'     => (int) env('DID_POLL_MAX_ATTEMPTS', 60),
    ],

    'zernio' => [
        'default_publish_endpoint' => env('ZERNIO_POST_ENDPOINT', '/v1/posts'),
        'publish_poll_attempts' => (int) env('ZERNIO_PUBLISH_POLL_ATTEMPTS', 15),
        'publish_poll_interval_seconds' => (int) env('ZERNIO_PUBLISH_POLL_INTERVAL_SECONDS', 3),
    ],

    'platform_content_limits' => [
        'tiktok_photo' => 90,
        'tiktok_video' => 2200,
        'tiktok_photo_description' => 4000,
        'youtube_title' => 100,
    ],
];
