<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Plugin;

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException;
use Polymorph\Platform\Http\Middleware\RequireCapability;
use Polymorph\Platform\Http\Middleware\VerifyApiCsrf;
use Polymorph\Sdk\Routing\RouteDefinition;
use Polymorph\Sdk\Routing\Routes;
use Polymorph\Sdk\Routing\Zone;
use Polymorph\Sdk\Routing\ZoneKind;

/** Validates and mounts SDK route definitions; any invalid route aborts bootstrap. */
final class PluginRouteMounter
{
    /**
     * Нейтральные символы снимаемых middleware → FQCN хоста.
     *
     * Расширение объявляет символ ('csrf'), не зная класс ядра: это держит
     * Polymorph\Platform вне поверхности SDK.
     *
     * @var array<string, class-string>
     */
    private const EXCLUDABLE = [
        'csrf' => VerifyApiCsrf::class,
    ];

    public function mountFile(string $pluginId, string $path): void
    {
        $this->mount($pluginId, PluginRoutes::fromFile($path));
    }

    public function mount(string $pluginId, Routes $routes): void
    {
        $this->validate($pluginId, $routes);

        foreach ($routes->zones() as $zone) {
            $this->mountZone($pluginId, $zone);
        }
    }

    private function validate(string $pluginId, Routes $routes): void
    {
        $seen = [];

        foreach ($routes->zones() as $zone) {
            $this->resolveExcluded($pluginId, $zone->withoutMiddleware);

            foreach ($zone->routes as $route) {
                [$controller, $method] = $route->action();
                if (! class_exists($controller) || ! method_exists($controller, $method)) {
                    throw new ExtensionException(
                        "Plugin '{$pluginId}' route '{$route->uri()}' references missing action {$controller}::{$method}.",
                    );
                }

                $this->resolveExcluded($pluginId, $route->withoutMiddlewareList());
                foreach ($route->methods() as $httpMethod) {
                    $key = $zone->kind->value.' '.$httpMethod.' '.trim($route->uri(), '/');
                    if (isset($seen[$key])) {
                        throw new ExtensionException(
                            "Plugin '{$pluginId}' declares duplicate route '{$httpMethod} {$route->uri()}' in zone '{$zone->kind->value}'.",
                        );
                    }
                    $seen[$key] = true;
                }
            }
        }
    }

    private function mountZone(string $pluginId, Zone $zone): void
    {
        $registrar = Route::prefix(self::prefix($zone->kind, $pluginId))
            ->name(self::namePrefix($zone->kind, $pluginId))
            ->middleware([...self::baseMiddleware($zone->kind), ...$zone->middleware]);

        $excluded = $this->resolveExcluded($pluginId, $zone->withoutMiddleware);
        if ($excluded !== []) {
            $registrar = $registrar->withoutMiddleware($excluded);
        }

        $zoneHasCapability = self::containsCapabilityMiddleware($zone->middleware);

        $registrar->group(function () use ($pluginId, $zone, $zoneHasCapability): void {
            foreach ($zone->routes as $route) {
                $this->mountRoute($pluginId, $route, $zone->kind, $zoneHasCapability);
            }
        });
    }

    private function mountRoute(string $pluginId, RouteDefinition $route, ZoneKind $kind, bool $zoneHasCapability): void
    {
        [$controller, $method] = $route->action();

        // Fail-closed дефолт админ-зоны: маршрут ADMIN_API, за который ни зона,
        // ни сам маршрут не объявили capability, получает ext.{id}.admin/access.
        // The admin zone is always browser-session authenticated; a forgotten capability
        // означал админ-эндпоинт, открытый любому аутентифицированному.
        $middleware = $route->middlewareList();
        if ($kind === ZoneKind::ADMIN_API
            && ! $zoneHasCapability
            && ! self::containsCapabilityMiddleware($middleware)) {
            $middleware = [RequireCapability::forRoute("ext.{$pluginId}.admin"), ...$middleware];
        }

        // Всё, что влияет на регистрацию, задаётся ДО match(): RouteCollection
        // наполняет таблицу имён в момент add(), поэтому имя, назначенное
        // fluent-вызовом после, в route() и Route::has() не попадает.
        $registrar = Route::middleware($middleware);

        $name = $route->relativeName();
        if ($name !== null) {
            $registrar = $registrar->name($name);
        }

        $excluded = $this->resolveExcluded($pluginId, $route->withoutMiddlewareList());
        if ($excluded !== []) {
            $registrar = $registrar->withoutMiddleware($excluded);
        }

        $where = $route->whereMap();
        if ($where !== []) {
            $registrar = $registrar->where($where);
        }

        $registrar->match($route->methods(), $route->uri(), [$controller, $method]);
    }

    /**
     * Префикс URI зоны. Подставляется ХОСТОМ из id расширения, поэтому выйти
     * за свою поверхность расширению нечем.
     */
    private static function prefix(ZoneKind $kind, string $pluginId): string
    {
        return match ($kind) {
            ZoneKind::API => "api/v1/ext/{$pluginId}",
            ZoneKind::ADMIN_API => "api/v1/admin/ext/{$pluginId}",
            ZoneKind::WEB => "ext/{$pluginId}",
        };
    }

    /**
     * Префикс имени маршрута. Тоже от хоста: занять имя маршрута ядра
     * (и увести route()) расширению нечем.
     */
    private static function namePrefix(ZoneKind $kind, string $pluginId): string
    {
        return match ($kind) {
            ZoneKind::API => "api.v1.ext.{$pluginId}.",
            ZoneKind::ADMIN_API => "admin.v1.ext.{$pluginId}.",
            ZoneKind::WEB => "ext.{$pluginId}.",
        };
    }

    /** @return list<string> */
    private static function baseMiddleware(ZoneKind $kind): array
    {
        return match ($kind) {
            ZoneKind::API => ['api'],
            ZoneKind::ADMIN_API => ['api', 'auth:session'],
            ZoneKind::WEB => ['web'],
        };
    }

    /**
     * @param  list<string>  $middleware
     */
    private static function containsCapabilityMiddleware(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if (is_string($entry) && str_starts_with($entry, RequireCapability::ALIAS.':')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $symbols
     * @return list<string>
     */
    private function resolveExcluded(string $pluginId, array $symbols): array
    {
        $resolved = [];

        foreach ($symbols as $symbol) {
            if (isset(self::EXCLUDABLE[$symbol])) {
                $resolved[] = self::EXCLUDABLE[$symbol];

                continue;
            }

            throw new ExtensionException(
                "Plugin '{$pluginId}' requests unsupported middleware exclusion '{$symbol}'.",
            );
        }

        return $resolved;
    }
}
