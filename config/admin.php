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
];
