<?php

declare(strict_types=1);

return [
    // Root directory holding the declarative route files. Package-local (the routes
    // moved into blyattebayo/polymorph, ADR 0006 §4.4), so the loaders resolve here instead
    // of the host's base_path('routes'). __DIR__ = platform/config → platform/routes.
    'base_path' => dirname(__DIR__).'/routes',

    'declarative_files' => [
        'web_core.php',
        'api.php',
        'api_plugins.php',
        'api_admin.php',
    ],
];
