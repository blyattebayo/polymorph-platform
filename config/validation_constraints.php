<?php

declare(strict_types=1);

return [
    'password' => [
        'min' => 8,
        'max' => 255,
    ],

    'email' => [
        'max' => 254,
        'laravel' => 'email:strict',
        'normalize' => 'lowercase',
    ],

    'slug' => [
        'max' => 255,
        'pattern' => '^[a-z][a-z0-9_-]*$',
    ],

    'roleCode' => [
        'max' => 100,
        'pattern' => '^[a-z][a-z0-9_.-]*$',
    ],

    'aclAction' => [
        'max' => 100,
        'pattern' => '^[a-z0-9._*-]+$',
    ],
];
