<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin emails (user management access)
    |--------------------------------------------------------------------------
    |
    | Comma-separated list in ADMIN_EMAILS. Users who sign in with one of
    | these addresses can open /users and see the Users item in the sidebar.
    |
    */

    'emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', ''))
    ))),

];
