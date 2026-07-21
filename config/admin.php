<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Admin SPA mount path
    |--------------------------------------------------------------------------
    |
    | Where the bundled admin panel is served. A host can relocate it (e.g.
    | 'manage') via ADMIN_PATH without rebuilding the frontend — the shell
    | controller injects this value at runtime and the client router + asset
    | URLs follow it. Leading/trailing slashes are optional.
    |
    */
    'path' => env('ADMIN_PATH', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Seeded administrator account
    |--------------------------------------------------------------------------
    |
    | Credentials used by Polymorph\Platform\Database\Seeders\AdminUserSeeder
    | (part of PlatformSeeder). Env-driven so `php artisan db:seed` gives a
    | working admin out of the box. The defaults are for LOCAL/DEMO only —
    | the seeder prints a loud warning when the default password is in use.
    |
    */
    'seed' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD', 'admin123'),
        'name' => env('ADMIN_NAME', 'Administrator'),
    ],
];
