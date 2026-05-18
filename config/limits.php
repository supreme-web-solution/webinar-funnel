<?php

return [

    'mentions' => [

        /** Max keywords per funnel traffic settings. */
        'max_keywords_per_funnel' => (int) env('MENTIONS_MAX_KEYWORDS_PER_FUNNEL', 5),

        /**
         * When stored mentions for a keyword reach this count, the keyword is auto-paused
         * and cannot be re-enabled until mentions are deleted below the cap.
         */
        'max_mentions_per_keyword' => (int) env('MENTIONS_MAX_RESULTS_PER_KEYWORD', 500),

    ],

    'fetch' => [

        /** Max keywords dispatched per platform per scheduler run (0 = no limit). */
        'keywords_per_cycle' => (int) env('MENTIONS_KEYWORDS_PER_FETCH_CYCLE', 0),

        'platform_intervals' => [
            'reddit' => (int) env('MENTIONS_FETCH_INTERVAL_REDDIT', 15),
            'youtube' => (int) env('MENTIONS_FETCH_INTERVAL_YOUTUBE', 30),
            'twitter' => (int) env('MENTIONS_FETCH_INTERVAL_TWITTER', 10),
            'news' => (int) env('MENTIONS_FETCH_INTERVAL_NEWS', 60),
        ],

    ],

];
