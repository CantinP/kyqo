<?php

/**
 * Example pluralisation strings — all three syntaxes.
 *
 * trans_choice('messages.apples', 0)  => 'No apples'
 * trans_choice('messages.apples', 1)  => 'One apple'
 * trans_choice('messages.apples', 5)  => '5 apples'
 *
 * trans_choice('messages.items', 1)   => '1 item'
 * trans_choice('messages.items', 3)   => '3 items'
 *
 * trans_choice('messages.files', 0)   => 'No files'
 * trans_choice('messages.files', 1)   => '1 file'
 * trans_choice('messages.files', 42)  => '42 files'
 */
return [
    // Pipe with explicit counts / ranges
    'apples'  => '{0} No apples|{1} One apple|[2,*] :count apples',

    // Pipe — two forms (singular|plural)
    'items'   => ':count item|:count items',

    // ICU-style
    'files'   => '{count, plural, =0{No files} one{# file} other{# files}}',

    // Three-form example
    'minutes' => '{0} Just now|{1} One minute ago|[2,*] :count minutes ago',
];
