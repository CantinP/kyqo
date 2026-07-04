<?php

return [

    'default' => env('LOG_CHANNEL', 'single'),

    'channels' => [

        'single' => [
            'driver' => 'single',
            'path'   => storage_path('logs/kyqo.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/kyqo.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'days'   => 14,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level'  => env('LOG_LEVEL', 'debug'),
        ],

    ],

];
