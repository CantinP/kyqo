<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    | Directories where Kyqo will look for view templates.
    | Dot-notation maps to directory separators: "emails.welcome" => emails/welcome.
    |
    */
    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    */
    'compiled' => storage_path('framework/views'),

    /*
    |--------------------------------------------------------------------------
    | View Caching
    |--------------------------------------------------------------------------
    | Set to false in development to always re-render templates.
    */
    'cache' => (bool) env('VIEW_CACHE', true),

];
