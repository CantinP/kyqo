<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    | Supported: 'bcrypt', 'argon', 'argon2id'
    |
    */
    'driver' => env('HASH_DRIVER', 'bcrypt'),

    'bcrypt' => [
        'rounds' => (int) env('BCRYPT_ROUNDS', 12),
    ],

    'argon' => [
        'memory'  => 65536,
        'threads' => 1,
        'time'    => 4,
    ],

];
