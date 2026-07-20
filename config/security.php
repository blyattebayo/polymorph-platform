<?php

return [
    'csrf' => [
        'allowed_origins' => array_values(array_filter(array_map(
            static fn (string $origin): string => rtrim(trim($origin), '/'),
            explode(',', env('CSRF_ALLOWED_ORIGINS', env('APP_URL', 'http://127.0.0.1:8000').','.env('FRONTEND_URL', 'http://127.0.0.1:5173')))
        ))),
    ],

];
