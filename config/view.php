<?php

return [

    'paths' => [
        resource_path('views'),
    ],

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views'))
    ),

    'components' => [
        'path'      => resource_path('components'),
        'namespace' => 'App\\View\\Components',
    ],

    'cache' => env('VIEW_CACHE', true),

];
