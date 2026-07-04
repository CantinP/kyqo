<?php

return [
    'required'  => 'The :attribute field is required.',
    'email'     => 'The :attribute must be a valid email address.',
    'min'       => [
        'string'  => 'The :attribute must be at least :min characters.',
        'numeric' => 'The :attribute must be at least :min.',
        'array'   => 'The :attribute must have at least :min items.',
    ],
    'max'       => [
        'string'  => 'The :attribute must not exceed :max characters.',
        'numeric' => 'The :attribute must not exceed :max.',
    ],
    'unique'    => 'The :attribute has already been taken.',
    'exists'    => 'The selected :attribute is invalid.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'numeric'   => 'The :attribute must be a number.',
    'integer'   => 'The :attribute must be an integer.',
    'boolean'   => 'The :attribute must be true or false.',
    'string'    => 'The :attribute must be a string.',
    'array'     => 'The :attribute must be an array.',
    'date'      => 'The :attribute must be a valid date.',
    'url'       => 'The :attribute must be a valid URL.',
    'ip'        => 'The :attribute must be a valid IP address.',
    'regex'     => 'The :attribute format is invalid.',
    'in'        => 'The selected :attribute is invalid.',
    'not_in'    => 'The selected :attribute is invalid.',
    'nullable'  => '',
];
