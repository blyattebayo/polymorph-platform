<?php

declare(strict_types=1);

return [
    // Гард задаётся в config/auth.php (auth.defaults.guard / AUTH_GUARD).
    // Здесь дубля больше нет: ключ jwt.default_guard не читался ниоткуда и
    // выглядел рабочей ручкой, ничего не меняя.
    'algo' => env('JWT_ALGO', 'HS256'),
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 15 * 60),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 30 * 24 * 60 * 60),

    // Абсолютный потолок жизни семьи refresh-сессий: ротация продлевает
    // expires_at, но никогда дальше created_at корня + refresh_family_ttl.
    'refresh_family_ttl' => (int) env('JWT_REFRESH_FAMILY_TTL', 90 * 24 * 60 * 60),

    // Максимум одновременных активных сессий на пользователя; при логине
    // сверх лимита старейшая сессия отзывается.
    'max_sessions_per_user' => (int) env('AUTH_MAX_SESSIONS_PER_USER', 20),
    'leeway' => (int) env('JWT_LEEWAY', 5),
    'secret' => env('JWT_SECRET', ''),
    'current_kid' => env('JWT_CURRENT_KID', 'default'),
    'keys' => array_filter([
        env('JWT_CURRENT_KID', 'default') => env('JWT_SECRET', ''),
        env('JWT_PREVIOUS_KID') => env('JWT_PREVIOUS_SECRET'),
    ], static fn ($secret, $kid): bool => is_string($kid) && $kid !== '' && is_string($secret) && $secret !== '', ARRAY_FILTER_USE_BOTH),
    'issuer' => env('JWT_ISS', 'https://polymorph.local'),
    'audience' => env('JWT_AUD', 'polymorph-api'),

    'cookies' => [
        'access' => env('JWT_ACCESS_COOKIE', 'cms_at'),
        'refresh' => env('JWT_REFRESH_COOKIE', 'cms_rt'),
        'domain' => env('JWT_COOKIE_DOMAIN', env('SESSION_DOMAIN')),
        'secure' => (bool) env('JWT_COOKIE_SECURE', env('APP_ENV') !== 'local'),
        'samesite' => env('JWT_COOKIE_SAMESITE', 'Strict'),
        'path' => env('JWT_COOKIE_PATH', '/'),
        'refresh_path' => env('JWT_REFRESH_COOKIE_PATH', '/api/v1/auth/refresh'),
    ],

];
