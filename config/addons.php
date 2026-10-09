<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Affiliate Campaign Vault (JVZoo 456183, 456187)
    |--------------------------------------------------------------------------
    */

    'affiliate_campaign_vault' => [
        'signup_url' => env('AFFILIATE_CAMPAIGN_VAULT_SIGNUP_URL', 'https://affilimachine.com/register'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Profit Multiplier (JVZoo 456221, 456223)
    |--------------------------------------------------------------------------
    |
    | Links without a URL render as "coming soon" on the page.
    |
    */

    'profit_multiplier' => [
        'links' => [
            [
                'label' => env('PROFIT_MULTIPLIER_LINK_1_LABEL', 'Profit Multiplier Link 1'),
                'url' => env('PROFIT_MULTIPLIER_LINK_1_URL'),
            ],
            [
                'label' => env('PROFIT_MULTIPLIER_LINK_2_LABEL', 'Profit Multiplier Link 2'),
                'url' => env('PROFIT_MULTIPLIER_LINK_2_URL'),
            ],
        ],
    ],

];
