<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dark mode
    |--------------------------------------------------------------------------
    |
    | When false, the app always uses light mode and the Appearance settings
    | page is hidden. Set APPEARANCE_DARK_MODE_ENABLED=true to allow users to
    | choose light, dark, or system preference.
    |
    */

    'dark_mode_enabled' => filter_var(
        env('APPEARANCE_DARK_MODE_ENABLED', false),
        FILTER_VALIDATE_BOOLEAN
    ),

];
