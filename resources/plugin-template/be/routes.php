<?php

declare(strict_types=1);

use Plugins\__PLUGIN_CLASS__\Http\Controllers\__PLUGIN_CLASS__HelloController;
use Polymorph\Sdk\Access\Capability;
use Polymorph\Sdk\Routing\RouteGroup;
use Polymorph\Sdk\Routing\Routes;

/**
 * Маршруты расширения.
 *
 * Объявляется зона и путь ВНУТРИ неё. Префикс пути и префикс имени подставляет
 * хост по id из extension.json:
 *
 *   api(...)      -> api/v1/ext/__PLUGIN_ID__/…        имя api.v1.ext.__PLUGIN_ID__.*
 *   adminApi(...) -> api/v1/admin/ext/__PLUGIN_ID__/…  имя admin.v1.ext.__PLUGIN_ID__.*
 *   web(...)      -> ext/__PLUGIN_ID__/…               имя ext.__PLUGIN_ID__.*
 *
 * Аутентификация зоны тоже от хоста: adminApi всегда идёт под auth:session.
 * Действие — всегда пара [Controller::class, 'method'] ('__invoke' для
 * invokable-контроллера).
 */
return Routes::define()
    ->adminApi(function (RouteGroup $routes): void {
        $routes->get('/hello', [__PLUGIN_CLASS__HelloController::class, '__invoke'])
            ->name('hello');
    }, requires: Capability::of('ext.__PLUGIN_ID__.access'));
