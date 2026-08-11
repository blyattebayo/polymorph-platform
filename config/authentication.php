<?php

declare(strict_types=1);

return [
    'cookies' => [
        'secure' => (bool) env('AUTH_COOKIE_SECURE', env('APP_ENV') !== 'local'),
        'samesite' => env('AUTH_COOKIE_SAMESITE', 'Strict'),
    ],
];
