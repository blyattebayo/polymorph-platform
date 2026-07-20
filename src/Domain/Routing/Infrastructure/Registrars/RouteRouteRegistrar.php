<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Registrars;

use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers\ActionResolverFactory;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Illuminate\Support\Facades\Route;

/**
 * Регистратор для ROUTE узлов маршрутов.
 *
 * Регистрирует конкретные маршруты через Route::match() с применением
 * настроек маршрута (name, domain, middleware, where, defaults)
 * и использованием ActionResolverFactory для разрешения действия.
 */
class RouteRouteRegistrar implements RouteNodeRegistrarInterface
{
    /**
     * Нейтральные символы исключаемого middleware → реальные host-FQCN.
     * Расширения (SDK) объявляют исключение символом (например 'csrf'), не зная
     * конкретный класс ядра; хост резолвит его здесь. Держит `Polymorph\Platform\*` вне поверхности SDK.
     * Сырой FQCN (легаси-артефакты, собранные до нейтрализации) проходит как есть.
     *
     * @var array<string, class-string>
     */
    private const EXCLUDABLE_MIDDLEWARE_SYMBOLS = [
        'csrf' => \Polymorph\Platform\Http\Middleware\VerifyApiCsrf::class,
    ];

    /**
     * @param  \Polymorph\Platform\Domain\Routing\Resolvers\ActionResolverFactory|null  $actionResolverFactory  Фабрика для разрешения действий
     */
    public function __construct(
        private readonly AppLogger $logger,
        private ?ActionResolverFactory $actionResolverFactory = null,
    ) {}

    /**
     * Зарегистрировать узел маршрута.
     *
     * @param  \Polymorph\Platform\Domain\Routing\Core\Models\RouteNode  $node  Узел маршрута
     */
    public function register(RouteNodeDefinition $node): void
    {
        if (! $this->validateRouteNode($node)) {
            return;
        }

        $methods = $node->methods;
        $uri = $node->uri;

        // Разрешаем действие через фабрику резолверов
        if ($this->actionResolverFactory === null) {
            $this->logger->error('routing.dynamic_route.action_resolver_factory_missing', [
                'route_node_id' => $node->sourceId,
            ]);

            return;
        }

        $action = $this->actionResolverFactory->resolve($node);
        if ($action === null) {
            return; // Ошибка уже залогирована в резолвере
        }

        // Проверяем существование контроллера перед регистрацией
        // Это предотвращает ошибки при route:list для несуществующих контроллеров
        if (! $this->validateAction($action, $node->sourceId, $uri)) {
            return;
        }

        $route = Route::match($methods, $uri, $action);

        // Применяем дополнительные настройки маршрута
        $this->applyRouteSettings($route, $node);
    }

    /**
     * Проверить валидность узла маршрута.
     *
     * @param  \Polymorph\Platform\Domain\Routing\Core\Models\RouteNode  $node  Узел маршрута
     * @return bool true если узел валиден, false иначе
     */
    private function validateRouteNode(RouteNodeDefinition $node): bool
    {
        if (! $node->uri || ! $node->methods) {
            $this->logger->warning('routing.dynamic_route.missing_uri_or_methods', [
                'route_node_id' => $node->sourceId,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Проверить валидность действия перед регистрацией.
     *
     * Проверяет существование контроллера и метода для предотвращения
     * ошибок при route:list для несуществующих контроллеров.
     *
     * @param  callable|string|array<string>  $action  Действие для проверки
     * @param  int|null  $routeNodeId  ID узла маршрута (для логирования), может быть null для декларативных маршрутов
     * @param  string  $uri  URI маршрута (для логирования)
     * @return bool true если действие валидно, false иначе
     */
    private function validateAction(callable|string|array $action, ?int $routeNodeId, string $uri): bool
    {
        if (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;
            if (! class_exists($controller)) {
                $this->logger->warning('routing.dynamic_route.controller_missing_skipped', [
                    'route_node_id' => $routeNodeId,
                    'controller' => $controller,
                    'uri' => $uri,
                ]);

                return false;
            }
            if (! method_exists($controller, $method)) {
                $this->logger->warning('routing.dynamic_route.method_missing_skipped', [
                    'route_node_id' => $routeNodeId,
                    'controller' => $controller,
                    'method' => $method,
                    'uri' => $uri,
                ]);

                return false;
            }
        } elseif (is_string($action) && ! class_exists($action)) {
            $this->logger->warning('routing.dynamic_route.invokable_controller_missing_skipped', [
                'route_node_id' => $routeNodeId,
                'controller' => $action,
                'uri' => $uri,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Применить настройки маршрута.
     *
     * Применяет все дополнительные настройки маршрута:
     * name, domain, middleware, where, defaults.
     *
     * @param  \Illuminate\Routing\Route  $route  Объект маршрута Laravel
     * @param  RouteNodeDefinition  $node  Узел маршрута
     */
    private function applyRouteSettings(\Illuminate\Routing\Route $route, RouteNodeDefinition $node): void
    {
        if ($node->name) {
            $route->name($node->name);
        }

        if ($node->domain) {
            $route->domain($node->domain);
        }

        $middleware = is_array($node->middleware) ? $node->middleware : [];
        if ($middleware !== []) {
            $routeMiddleware = [];
            $withoutMiddleware = [];

            foreach ($middleware as $entry) {
                if (! is_string($entry) || trim($entry) === '') {
                    continue;
                }

                if (str_starts_with($entry, 'without:')) {
                    $excluded = trim(substr($entry, strlen('without:')));
                    if ($excluded !== '') {
                        $withoutMiddleware[] = self::EXCLUDABLE_MIDDLEWARE_SYMBOLS[$excluded] ?? $excluded;
                    }

                    continue;
                }

                $routeMiddleware[] = $entry;
            }

            if ($routeMiddleware !== []) {
                $route->middleware($routeMiddleware);
            }

            if ($withoutMiddleware !== []) {
                $route->withoutMiddleware($withoutMiddleware);
            }
        }

        if ($node->where) {
            foreach ($node->where as $param => $pattern) {
                $route->where($param, $pattern);
            }
        }

        if ($node->defaults) {
            foreach ($node->defaults as $key => $value) {
                $route->defaults($key, $value);
            }
        }
    }
}
