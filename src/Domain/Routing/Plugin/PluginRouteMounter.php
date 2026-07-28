<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Plugin;

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Http\Middleware\VerifyApiCsrf;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Polymorph\Sdk\Routing\RouteDefinition;
use Polymorph\Sdk\Routing\Routes;
use Polymorph\Sdk\Routing\Zone;
use Polymorph\Sdk\Routing\ZoneKind;
use Throwable;

/**
 * Монтирует маршруты расширений в роутер.
 *
 * Гранулярность отказа задаётся здесь и нигде больше:
 * - битый файл или исключение при сборке — выпадает ОДНО расширение;
 * - несуществующий контроллер или метод — выпадает ОДИН маршрут;
 * - каталог расширений и маршруты ядра не выключаются никогда.
 *
 * Один и тот же путь используется и на бутстрапе, и при включении расширения
 * в живом роутере, поэтому «работает при старте, но не работает при enable»
 * (и наоборот) структурно невозможно.
 */
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

    public function __construct(
        private readonly PluginRouteCatalog $catalog,
        private readonly AppLogger $logger,
    ) {}

    /**
     * Смонтировать маршруты всех включённых расширений.
     */
    public function mountEnabled(): void
    {
        foreach ($this->catalog->enabled() as $file) {
            $this->mountFile($file);
        }
    }

    /**
     * Смонтировать одно расширение. Его падение не задевает остальных.
     */
    public function mountFile(PluginRouteFile $file): void
    {
        try {
            $this->mount($file->pluginId, PluginRoutes::fromFile($file->path));
        } catch (Throwable $exception) {
            $this->logger->error('routing.plugin.mount_failed', [
                'plugin_id' => $file->pluginId,
                'path' => $file->path,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Есть ли маршруты этого расширения в текущем роутере.
     *
     * Проверяем по префиксу пути, а не по именам: имя необязательно, а префикс
     * задаёт хост и он уникален для расширения — значит, признак не зависит
     * от того, что расширение написало в своём файле.
     */
    public function isMounted(string $pluginId): bool
    {
        $prefixes = array_map(
            static fn (ZoneKind $kind): string => self::prefix($kind, $pluginId),
            ZoneKind::cases(),
        );

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');

            foreach ($prefixes as $prefix) {
                // Точное совпадение — маршрут в корне зоны; со слэшем — всё
                // остальное. Голый str_starts_with без слэша считал бы
                // расширение 'demo' смонтированным по маршрутам 'demo-2'.
                if ($uri === $prefix || str_starts_with($uri, $prefix.'/')) {
                    return true;
                }
            }
        }

        return false;
    }

    public function mount(string $pluginId, Routes $routes): void
    {
        foreach ($routes->zones() as $zone) {
            $this->mountZone($pluginId, $zone);
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

        $registrar->group(function () use ($pluginId, $zone): void {
            foreach ($zone->routes as $route) {
                $this->mountRoute($pluginId, $route);
            }
        });
    }

    private function mountRoute(string $pluginId, RouteDefinition $route): void
    {
        [$controller, $method] = $route->action();

        // Проверяем ЗДЕСЬ, а не при сборке: несуществующий метод у invokable
        // роняет весь роутер исключением из bootstrap, а пропуск одного
        // маршрута оставляет расширение работоспособным.
        if (! class_exists($controller) || ! method_exists($controller, $method)) {
            $this->logger->warning('routing.plugin.action_missing', [
                'plugin_id' => $pluginId,
                'controller' => $controller,
                'method' => $method,
                'uri' => $route->uri(),
            ]);

            return;
        }

        // Всё, что влияет на регистрацию, задаётся ДО match(): RouteCollection
        // наполняет таблицу имён в момент add(), поэтому имя, назначенное
        // fluent-вызовом после, в route() и Route::has() не попадает.
        $registrar = Route::middleware($route->middlewareList());

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
            ZoneKind::ADMIN_API => ['api', 'auth:api'],
            ZoneKind::WEB => ['web'],
        };
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

            // Неизвестный символ молча не снял бы ничего — это ровно тот класс
            // ошибок, из-за которого withoutCsrf() на уровне группы не работал.
            $this->logger->warning('routing.plugin.unknown_excludable_middleware', [
                'plugin_id' => $pluginId,
                'symbol' => $symbol,
                'known' => array_keys(self::EXCLUDABLE),
            ]);
        }

        return $resolved;
    }
}
