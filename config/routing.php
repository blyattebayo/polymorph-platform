<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Движок маршрутизации
    |--------------------------------------------------------------------------
    |
    | v1 — маршруты описаны массивами и собираются в дерево (Domain\Routing).
    | v2 — маршруты ядра описаны нативным Laravel, маршруты плагинов приезжают
    |      типизированными объектами SDK (Domain\RoutingV2).
    |
    | Выбор делает PlatformServiceProvider: регистрируется ровно один провайдер,
    | поэтому движки никогда не работают одновременно.
    |
    */
    'engine' => env('ROUTING_ENGINE', 'v1'),

    // --- Ниже: только для engine=v1 ---

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
