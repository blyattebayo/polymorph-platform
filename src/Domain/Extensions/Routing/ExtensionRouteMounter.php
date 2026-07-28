<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Routing;

use Polymorph\Platform\Domain\Extensions\Core\Contracts\ExtensionRoutes;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException as PluginException;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;
use Polymorph\Platform\Domain\RoutingV2\Plugin\PluginRouteMounter;
use Polymorph\Platform\Domain\RoutingV2\Plugin\PluginRoutes;
use Polymorph\Sdk\Routing\Routes;

/**
 * Маршруты расширения в жизненном цикле для движка v2.
 *
 * От v1-проверок остался ровно один вид: дубль внутри самого файла. Выйти за
 * свою поверхность или столкнуться с чужим маршрутом расширение больше не
 * может — префикс пути и префикс имени подставляет хост из id расширения,
 * поэтому проверять это нечем и незачем.
 */
final class ExtensionRouteMounter implements ExtensionRoutes
{
    public function __construct(
        private readonly PluginRouteMounter $mounter,
    ) {}

    /**
     * Битый файл маршрутов обязан валить enable ГРОМКО и до транзакции:
     * включённое расширение без работающих маршрутов — худший исход, чем
     * невключённое с понятной ошибкой.
     */
    public function validate(DiscoveredExtension $plugin): void
    {
        if ($plugin->backendRouteFile === null) {
            return;
        }

        self::assertNoDuplicates($plugin->id, PluginRoutes::fromFile($plugin->backendRouteFile));
    }

    public function mountInCurrentRouter(DiscoveredExtension $plugin): void
    {
        if ($plugin->backendRouteFile === null || $this->mounter->isMounted($plugin->id)) {
            return;
        }

        $this->mounter->mount($plugin->id, PluginRoutes::fromFile($plugin->backendRouteFile));
    }

    /**
     * Два маршрута с одинаковыми method+uri: RouteCollection пишет по ключу и
     * молча оставляет последний, поэтому половина объявленного просто исчезнет.
     * То же самое по всему приложению ловит `artisan routing:lint`, но здесь
     * оно должно всплыть при enable, а не при следующем прогоне CI.
     */
    private static function assertNoDuplicates(string $pluginId, Routes $routes): void
    {
        $seen = [];

        foreach ($routes->zones() as $zone) {
            foreach ($zone->routes as $route) {
                foreach ($route->methods() as $method) {
                    $key = $zone->kind->value.' '.$method.' '.trim($route->uri(), '/');

                    if (isset($seen[$key])) {
                        throw new PluginException(
                            "Plugin '{$pluginId}' declares duplicate route '{$method} {$route->uri()}' in zone '{$zone->kind->value}'.",
                        );
                    }

                    $seen[$key] = true;
                }
            }
        }
    }
}
