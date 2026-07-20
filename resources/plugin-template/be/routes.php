<?php

declare(strict_types=1);

use Polymorph\Sdk\Access\Capability;
use Polymorph\Sdk\Extension\ExtensionContext;
use Polymorph\Sdk\Routing\Middleware;
use Polymorph\Sdk\Routing\RouteGroup;
use Polymorph\Sdk\Routing\Routes;
use Plugins\__PLUGIN_CLASS__\Http\Controllers\__PLUGIN_CLASS__HelloController;

return Routes::for(ExtensionContext::for('__PLUGIN_ID__'))
    ->adminApi(function (RouteGroup $routes): void {
        $routes->get('/hello', __PLUGIN_CLASS__HelloController::class)
            ->name('hello');
    }, middleware: [Middleware::JWT_AUTH], requires: Capability::of('ext.__PLUGIN_ID__.access'))
    ->toArray();
