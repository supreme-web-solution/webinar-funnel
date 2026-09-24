<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tutorial page content
    |--------------------------------------------------------------------------
    |
    | Add sections here (shown on /tutorial). Each section supports:
    | - title (string, required)
    | - body (string, optional — HTML not supported; use plain text / line breaks)
    | - video_url (string, optional — YouTube/Vimeo embed URL or direct link)
    |
    */

    // Intro text is built in TutorialController using config('app.name').
    'intro' => '',

    'sections' => [
        [
            'title' => 'Desktop & Settings',
            'video_url' => 'https://youtu.be/CtonhLehd20',
        ],
        [
            'title' => 'Templates, Funnel & Webinar Intro',
            'video_url' => 'https://youtu.be/ylLN6q-zpNY',
        ],
        [
            'title' => 'Integration, Promotion & Ads',
            'video_url' => 'https://youtu.be/Do50lfQzryI',
        ],
        [
            'title' => 'Webinar Room & Chat',
            'video_url' => 'https://youtu.be/Fuc9hDYyZio',
        ],
    ],

];
