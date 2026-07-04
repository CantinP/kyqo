<?php

return [
    'required'  => 'Le champ :attribute est obligatoire.',
    'email'     => 'Le champ :attribute doit être une adresse e-mail valide.',
    'min'       => [
        'string'  => 'Le champ :attribute doit contenir au moins :min caractères.',
        'numeric' => 'Le champ :attribute doit être au moins égal à :min.',
    ],
    'max'       => [
        'string'  => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'unique'    => 'Le champ :attribute est déjà utilisé.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'numeric'   => 'Le champ :attribute doit être un nombre.',
];
