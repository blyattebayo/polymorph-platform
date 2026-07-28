<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure;

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers\RouteActionResolver;
use Polymorph\Platform\Domain\Routing\Services\MergedRouteTreeService;
use Polymorph\Platform\Http\Middleware\VerifyApiCsrf;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Регистрация дерева маршрутов в Laravel Router.
 *
 * Единственная точка «RouteNodeDefinition → Route»: и группы, и конечные
 * маршруты. Набор видов узлов закрыт enum-колонкой kind в route_nodes, поэтому
 * здесь match по enum, а не реестр регистраторов — новый вид невозможен без
 * миграции схемы.
 */
final class RouteRegistrar
{
    /**
     * Префикс, которым узел объявляет исключение middleware.
     */
    private const WITHOUT_PREFIX = 'without:';

    /**
     * Нейтральные символы исключаемого middleware → реальные host-FQCN.
     *
     * Расширения (SDK) объявляют исключение символом (например 'csrf'), не зная
     * конкретный класс ядра; хост резолвит его здесь. Держит `Polymorph\Platform\*`
     * вне поверхности SDK. Сырой FQCN (легаси-артефакты, собранные до
     * нейтрализации) проходит как есть.
     *
     * @var array<string, class-string>
     */
    private const EXCLUDABLE_MIDDLEWARE_SYMBOLS = [
        'csrf' => VerifyApiCsrf::class,
    ];

    public function __construct(
        private readonly MergedRouteTreeService $mergedTreeService,
        private readonly RouteActionResolver $actionResolver,
        private readonly AppLogger $logger,
    ) {}

    /**
     * Зарегистрировать все маршруты (системные, плагинные и клиентские).
     */
    public function registerAllRoutes(): void
    {
        $this->registerNodes($this->mergedTreeService->getEnabledTree());
    }

    /**
     * Зарегистрировать набор узлов вместе с их поддеревьями.
     *
     * Публичная точка входа: используется и на бутстрапе, и при вживлении
     * маршрутов плагина в живой роутер сразу после enable/update.
     *
     * @param  iterable<RouteNodeDefinition>  $nodes
     */
    public function registerNodes(iterable $nodes): void
    {
        $this->registerSubtree($nodes);

        // Имя маршрута выставляется fluent-вызовом ПОСЛЕ добавления в коллекцию,
        // а RouteCollection::addLookups() наполняет таблицу имён в момент add().
        // Без явного обновления getByName()/Route::has()/route() не находят ни
        // один динамический маршрут.
        Route::getRoutes()->refreshNameLookups();
    }

    /**
     * @param  iterable<RouteNodeDefinition>  $nodes
     */
    private function registerSubtree(iterable $nodes): void
    {
        foreach ($nodes as $node) {
            // Источники фильтруют по enabled только корни, поэтому выключенный
            // вложенный узел декларативного/плагинного дерева до этой проверки
            // всё равно регистрировался.
            if (! $node->enabled) {
                continue;
            }

            match ($node->kind) {
                RouteNodeKind::GROUP => $this->registerGroup($node),
                RouteNodeKind::ROUTE => $this->registerRoute($node),
            };
        }
    }

    private function registerGroup(RouteNodeDefinition $node): void
    {
        Route::group($this->groupAttributes($node), function () use ($node): void {
            $this->registerSubtree($node->children);
        });
    }

    private function registerRoute(RouteNodeDefinition $node): void
    {
        if ($node->uri === null || $node->methods === []) {
            $this->logger->warning('routing.route.missing_uri_or_methods', [
                'route_node_id' => $node->sourceId,
            ]);

            return;
        }

        $action = $this->actionResolver->resolve($node);

        // null — узел не подлежит регистрации; причина уже в логе резолвера.
        // URI достаётся fallback'у, то есть отдаёт структурированный 404.
        if ($action === null) {
            return;
        }

        $route = Route::match($node->methods, $node->uri, $action);

        if ($node->name !== null) {
            $route->name($node->name);
        }

        if ($node->domain !== null) {
            $route->domain($node->domain);
        }

        [$middleware, $excluded] = $this->partitionMiddleware($node->middleware);

        if ($middleware !== []) {
            $route->middleware($middleware);
        }

        if ($excluded !== []) {
            $route->withoutMiddleware($excluded);
        }

        foreach ($node->where as $param => $pattern) {
            $route->where($param, $pattern);
        }

        foreach ($node->defaults as $key => $value) {
            $route->defaults($key, $value);
        }
    }

    /**
     * Атрибуты для Route::group().
     *
     * Примечание: `name` группы намеренно не отображается в атрибут `as` —
     * это изменило бы имена всех дочерних маршрутов. Сегодня ни один источник
     * групповое имя не задаёт.
     *
     * @return array<string, mixed>
     */
    private function groupAttributes(RouteNodeDefinition $node): array
    {
        [$middleware, $excluded] = $this->partitionMiddleware($node->middleware);

        $attributes = [
            'prefix' => $node->prefix,
            'domain' => $node->domain,
            'namespace' => $node->namespace,
            'middleware' => $middleware === [] ? null : $middleware,
            // Групповой 'without:' раньше уезжал в Route::group() дословно и
            // регистрировался как несуществующий алиас middleware — то есть
            // Middleware::withoutCsrf() из SDK на уровне группы не работал.
            'excluded_middleware' => $excluded === [] ? null : $excluded,
            'where' => $node->where === [] ? null : $node->where,
        ];

        return array_filter($attributes, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Разделить список middleware на применяемые и исключаемые.
     *
     * @param  list<string>  $middleware
     * @return array{0: list<string>, 1: list<string>}
     */
    private function partitionMiddleware(array $middleware): array
    {
        $applied = [];
        $excluded = [];

        foreach ($middleware as $entry) {
            if (! is_string($entry) || trim($entry) === '') {
                continue;
            }

            if (! str_starts_with($entry, self::WITHOUT_PREFIX)) {
                $applied[] = $entry;

                continue;
            }

            $symbol = trim(substr($entry, strlen(self::WITHOUT_PREFIX)));

            if ($symbol !== '') {
                $excluded[] = self::EXCLUDABLE_MIDDLEWARE_SYMBOLS[$symbol] ?? $symbol;
            }
        }

        return [$applied, $excluded];
    }
}
