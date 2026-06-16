<?php

return [
    'enabled' => env('PROMOTION_ENABLED', true),

    'default_timezone' => env('PROMOTION_DEFAULT_TIMEZONE', 'UTC'),

    'default_sequence_size' => (int) env('PROMOTION_DEFAULT_SEQUENCE_SIZE', 12),

    'max_sequence_size' => (int) env('PROMOTION_MAX_SEQUENCE_SIZE', 30),

    'supported_platforms' => ['twitter', 'youtube', 'reddit'],

    'queues' => [
        'generate' => env('PROMOTION_QUEUE_GENERATE', 'promotion-generate'),
        'publish' => env('PROMOTION_QUEUE_PUBLISH', 'promotion-publish'),
    ],

    'openai' => [
        'text_model'  => env('PROMOTION_OPENAI_TEXT_MODEL',  'gpt-4o-mini'),
        'image_model' => env('PROMOTION_OPENAI_IMAGE_MODEL', 'gpt-image-1'),
        'timeout'     => (int) env('PROMOTION_OPENAI_TIMEOUT', 90),
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
    ],
];
