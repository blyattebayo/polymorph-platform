<?php

declare(strict_types=1);

return [
    'cookies' => [
        'secure' => (bool) env('AUTH_COOKIE_SECURE', env('APP_ENV') !== 'local'),
        'samesite' => env('AUTH_COOKIE_SAMESITE', 'Strict'),
    ],
    'oauth' => [
        // Public origin advertised to MCP clients. It may differ from APP_URL
        // when the OAuth/MCP surface is exposed through a dedicated hostname.
        'public_url' => env('OAUTH_PUBLIC_URL'),
    ],
];
