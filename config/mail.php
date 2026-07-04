<?php

return [

    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [
        'smtp' => [
            'transport'  => 'smtp',
            'host'       => env('MAIL_HOST', '127.0.0.1'),
            'port'       => (int) env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username'   => env('MAIL_USERNAME'),
            'password'   => env('MAIL_PASSWORD'),
            'timeout'    => 30,
        ],
        'log' => [
            'transport' => 'log',
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@kyqo.dev'),
        'name'    => env('MAIL_FROM_NAME', env('APP_NAME', 'Kyqo')),
    ],

];
