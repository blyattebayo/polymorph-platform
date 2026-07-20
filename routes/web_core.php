<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;

/**
 * Декларативные маршруты для системных веб-маршрутов.
 *
 * Эти маршруты загружаются автоматически и имеют приоритет над маршрутами из БД.
 * Используются для статических системных маршрутов, которые не должны изменяться через UI.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    // Группа для системных веб-маршрутов
    // Middleware: web
    // sort_order = -1000 (первый в порядке регистрации)
    [
        'kind' => RouteNodeKind::GROUP,
        'sort_order' => -1000,
        'middleware' => ['web'],
        'children' => [
            // Главная страница
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\Routing\Http\Controllers\HomeController',
                ],
                'name' => 'home',
            ],
            // FE-ассеты плагина из его собственной папки (dirname(manifest)/fe/dist).
            // Плагины монтируются только встроенно (embedded) в админ-SPA; ядро
            // отдаёт их бандл по этому пути через PluginAssetController.
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => 'plugins/{plugin}/fe/{path}',
                'methods' => ['GET'],
                'where' => ['path' => '.*'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Domain\Extensions\Http\Controllers\ExtensionAssetController@show',
                ],
                'name' => 'plugins.fe-asset',
            ],
            // Тестовые маршруты (только для testing окружения)
            // Примечание: условная загрузка по окружению удалена вместе с полем options
            // Если нужна условная загрузка, используйте прямой роутинг в RouteServiceProvider
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/admin/ping',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action_meta' => [
                    'action' => 'Polymorph\Platform\Http\Controllers\AdminPingController@ping',
                ],
            ],
            // Тестовый маршрут для проверки авторизации (только для testing)
            // Используется в тестах, оставлен в старом формате routes/web_core.php
            // так как требует анонимную функцию, которая не может быть сериализована
        ],
    ],
];

