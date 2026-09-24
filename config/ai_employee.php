<?php

return [

    'name' => env('AI_EMPLOYEE_NAME', 'Alex'),
    'title' => env('AI_EMPLOYEE_TITLE', 'Command Center'),
    /** Public path (under /public) or absolute URL for Command Center avatar */
    'avatar' => env('AI_EMPLOYEE_AVATAR', '/images/ai-employee/alex.png'),
    'queue' => env('AI_EMPLOYEE_QUEUE', 'webinar-ai'),
    'history_limit' => (int) env('AI_EMPLOYEE_HISTORY_LIMIT', 40),

    'autonomy' => [
        'default' => env('AI_EMPLOYEE_AUTONOMY', 'assisted'),
        'levels' => ['copilot', 'assisted', 'autopilot'],
    ],

    'failover' => [
        'openrouter' => env('AI_EMPLOYEE_MODEL', env('OPENROUTER_MODEL', 'openai/gpt-4o-mini')),
        'openai' => env('AI_EMPLOYEE_OPENAI_MODEL', env('OPENAI_CHAT_MODEL', 'gpt-4o-mini')),
        'gemini' => env('AI_EMPLOYEE_GEMINI_MODEL', 'gemini-2.0-flash'),
        'anthropic' => env('AI_EMPLOYEE_ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
    ],

    'whatsapp' => [
        'account_id' => env('ZERNIO_WHATSAPP_ACCOUNT_ID'),
        'phone' => env('ZERNIO_WHATSAPP_PHONE'),
        'webhook_secret' => env('ZERNIO_WEBHOOK_SECRET'),
        'pairing_ttl_minutes' => (int) env('AI_EMPLOYEE_WHATSAPP_PAIRING_TTL', 30),
    ],

    'suggestions' => [
        'Show my campaign status',
        'What needs my attention?',
        'Find ClickBank offers for keto',
        'Summarize this offer page: https://',
        'Create a tracked link to my offer',
        'Plan this week’s content',
        'help',
    ],

];
