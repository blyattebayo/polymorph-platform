<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Routing\Access\RoutingCapabilityProvider;
use Polymorph\Platform\Domain\Routing\Core\Contracts\PluginRouteCatalog;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;
use Polymorph\Platform\Domain\Routing\Events\RouteNodeCreated;
use Polymorph\Platform\Domain\Routing\Events\RouteNodeDeleted;
use Polymorph\Platform\Domain\Routing\Events\RouteNodeUpdated;
use Polymorph\Platform\Domain\Routing\Http\Controllers\FallbackController;
use Polymorph\Platform\Domain\Routing\Infrastructure\Builders\RouteGroupNodeBuilder;
use Polymorph\Platform\Domain\Routing\Infrastructure\Builders\RouteNodeBuilderFactory;
use Polymorph\Platform\Domain\Routing\Infrastructure\Builders\RouteRouteNodeBuilder;
use Polymorph\Platform\Domain\Routing\Infrastructure\Loaders\PluginRouteLoader;
use Polymorph\Platform\Domain\Routing\Infrastructure\Loaders\RouteDefinitionLoader;
use Polymorph\Platform\Domain\Routing\Infrastructure\Loaders\SystemRouteLoader;
use Polymorph\Platform\Domain\Routing\Infrastructure\Registrars\RouteGroupRegistrar;
use Polymorph\Platform\Domain\Routing\Infrastructure\Registrars\RouteNodeRegistrarFactory;
use Polymorph\Platform\Domain\Routing\Infrastructure\Registrars\RouteRouteRegistrar;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\ClientRouteRepository;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\PluginRouteRepository;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\SystemRouteRepository;
use Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers\ActionResolverFactory;
use Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers\ControllerActionResolver;
use Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers\RedirectActionResolver;
use Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers\ViewActionResolver;
use Polymorph\Platform\Domain\Routing\Infrastructure\RouteRegistrar;
use Polymorph\Platform\Domain\Routing\Infrastructure\Validators\GroupNodeValidator;
use Polymorph\Platform\Domain\Routing\Infrastructure\Validators\RouteNodeValidator;
use Polymorph\Platform\Domain\Routing\Infrastructure\Validators\RouteValidator;
use Polymorph\Platform\Domain\Routing\Infrastructure\Validators\ValidatorRegistry;
use Polymorph\Platform\Domain\Routing\Listeners\LogRouteNodeLifecycle;
use Polymorph\Platform\Domain\Routing\Observers\RouteNodeObserver;
use Polymorph\Platform\Domain\Routing\Services\Cache\RouteCache;
use Polymorph\Platform\Domain\Routing\Services\MergedRouteTreeService;
use Polymorph\Platform\Domain\Routing\Services\RouteNodeService;
use Polymorph\Platform\Domain\Routing\Services\RouteNodeServiceInterface;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Service Provider для модуля Routing.
 *
 * Регистрирует все сервисы доменного модуля маршрутизации:
 * - Валидаторы и билдеры узлов маршрутов
 * - Резолверы действий и регистраторы
 * - Загрузчики, репозитории и сервисы
 *
 * Загружает маршруты в детерминированном порядке:
 * 1) Core в†’ 2) Public API в†’ 3) Admin API в†’ 4) Content в†’ 5) Dynamic Routes в†’ 6) Fallback
 */
class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Путь к "home" маршруту приложения.
     *
     * Обычно пользователи перенаправляются сюда после аутентификации.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Регистрация сервисов в DI контейнере.
     *
     * Оптимизированная регистрация с явным объявлением всех зависимостей.
     * Валидаторы и билдеры вынесены в отдельные сервисы для переиспользования.
     */
    public function register(): void
    {
        // 1. Базовые сервисы без зависимостей

        $this->app->singleton(RouteCache::class);

        // 2. Валидаторы (stateless, но регистрируем для возможности расширения)

        $this->app->singleton(GroupNodeValidator::class);
        $this->app->singleton(RouteNodeValidator::class);

        // 3. Реестр валидаторов

        $this->app->singleton(ValidatorRegistry::class, function ($app) {
            $registry = new ValidatorRegistry;
            $registry->register(
                RouteNodeKind::GROUP->value,
                $app->make(GroupNodeValidator::class)
            );
            $registry->register(
                RouteNodeKind::ROUTE->value,
                $app->make(RouteNodeValidator::class)
            );

            return $registry;
        });

        // 4. Валидатор для декларативных маршрутов

        $this->app->singleton(RouteValidator::class, function ($app) {
            return new RouteValidator(
                $app->make(ValidatorRegistry::class)
            );
        });

        // 5. Билдеры узлов маршрутов (stateless, переиспользуемые)

        $this->app->singleton(RouteGroupNodeBuilder::class);
        $this->app->singleton(RouteRouteNodeBuilder::class);

        // 6. Резолверы действий маршрутов (stateless, переиспользуемые)

        $this->app->singleton(ViewActionResolver::class);
        $this->app->singleton(RedirectActionResolver::class);
        $this->app->singleton(ControllerActionResolver::class);

        // 7. Фабрики узлов маршрутов (композиция стратегий вынесена в метод)
        $this->registerNodeFactories();

        // 8. Загрузчики маршрутов (теперь доступны как отдельные сервисы)

        $this->app->singleton(RouteDefinitionLoader::class, function ($app) {
            return new RouteDefinitionLoader(
                $app->make(RouteValidator::class),
                $app->make(RouteNodeBuilderFactory::class),
                $app->make(AppLogger::class),
            );
        });

        $this->app->singleton(SystemRouteLoader::class, function ($app) {
            return new SystemRouteLoader(
                $app->make(RouteDefinitionLoader::class)
            );
        });

        $this->app->singleton(PluginRouteLoader::class, function ($app) {
            return new PluginRouteLoader(
                $app->make(PluginRouteCatalog::class),
                $app->make(RouteDefinitionLoader::class),
                $app->make(AppLogger::class),
            );
        });

        // 9. Репозитории

        $this->app->singleton(ClientRouteRepository::class, function ($app) {
            return new ClientRouteRepository(
                $app->make(RouteCache::class),
                $app->make(AppLogger::class),
            );
        });

        $this->app->singleton(SystemRouteRepository::class, function ($app) {
            return new SystemRouteRepository(
                $app->make(SystemRouteLoader::class),
                $app->make(RouteCache::class)
            );
        });

        $this->app->singleton(PluginRouteRepository::class, function ($app) {
            return new PluginRouteRepository(
                $app->make(PluginRouteLoader::class),
                $app->make(RouteCache::class)
            );
        });

        // 10. Сервисы высокого уровня

        $this->app->singleton(MergedRouteTreeService::class, function ($app) {
            // Список источников дерева (RouteTreeSource). Порядок не влияет на
            // итог (сортировка по приоритету владельца), задан для читаемости.
            return new MergedRouteTreeService([
                $app->make(SystemRouteRepository::class),
                $app->make(PluginRouteRepository::class),
                $app->make(ClientRouteRepository::class),
            ]);
        });

        $this->app->singleton(RouteRegistrar::class, function ($app) {
            return new RouteRegistrar(
                $app->make(MergedRouteTreeService::class),
                $app->make(RouteNodeRegistrarFactory::class)
            );
        });

        // 11. Интерфейсы

        $this->app->singleton(
            RouteNodeServiceInterface::class,
            RouteNodeService::class
        );
    }

    /**
     * Регистрация фабрик узлов маршрутов вместе с их стратегиями
     * (билдеры/резолверы/регистраторы). Вынесено из register() для читаемости —
     * композиция стратегий в одном месте, register() остаётся обзором биндингов.
     */
    private function registerNodeFactories(): void
    {
        // RouteNodeBuilderFactory — с внедрёнными билдерами
        $this->app->singleton(RouteNodeBuilderFactory::class, function ($app) {
            $factory = new RouteNodeBuilderFactory($app->make(AppLogger::class));
            $factory->register(
                RouteNodeKind::GROUP,
                $app->make(RouteGroupNodeBuilder::class)
            );
            $factory->register(
                RouteNodeKind::ROUTE,
                $app->make(RouteRouteNodeBuilder::class)
            );

            return $factory;
        });

        // ActionResolverFactory — с внедрёнными резолверами
        $this->app->singleton(ActionResolverFactory::class, function ($app) {
            $factory = new ActionResolverFactory($app->make(AppLogger::class));
            $factory->setResolvers([
                RouteNodeActionType::VIEW->value => $app->make(ViewActionResolver::class),
                RouteNodeActionType::REDIRECT->value => $app->make(RedirectActionResolver::class),
                RouteNodeActionType::CONTROLLER->value => $app->make(ControllerActionResolver::class),
            ]);

            return $factory;
        });

        // RouteNodeRegistrarFactory — с рекурсивными зависимостями
        $this->app->singleton(RouteNodeRegistrarFactory::class, function ($app) {
            $factory = new RouteNodeRegistrarFactory($app->make(AppLogger::class));

            // RouteGroupRegistrar требует ссылку на саму фабрику (рекурсия)
            $groupRegistrar = new RouteGroupRegistrar($app->make(AppLogger::class), $factory);
            $factory->register(RouteNodeKind::GROUP, $groupRegistrar);

            // RouteRouteRegistrar требует ActionResolverFactory
            $routeRegistrar = new RouteRouteRegistrar(
                $app->make(AppLogger::class),
                $app->make(ActionResolverFactory::class),
            );
            $factory->register(RouteNodeKind::ROUTE, $routeRegistrar);

            return $factory;
        });
    }

    /**
     * Определить привязки моделей, фильтры паттернов и другую конфигурацию маршрутов.
     *
     * Загружает маршруты в определённом порядке.
     */
    public function boot(): void
    {
        // Регистрация Observer для RouteNode модели
        RouteNode::observe(RouteNodeObserver::class);

        // Логирование жизненного цикла узлов маршрутов — листенер на доменных
        // событиях (раньше RouteNodeService логировал инлайн строкой).
        Event::listen(RouteNodeCreated::class, [LogRouteNodeLifecycle::class, 'handleCreated']);
        Event::listen(RouteNodeUpdated::class, [LogRouteNodeLifecycle::class, 'handleUpdated']);
        Event::listen(RouteNodeDeleted::class, [LogRouteNodeLifecycle::class, 'handleDeleted']);

        // Порядок загрузки роутов (детерминированный):
        // 1) Core в†’ 2) Public API в†’ 3) Admin API в†’ 4) Content в†’ 5) Dynamic Routes в†’ 6) Fallback

        // 1-5) Декларативные и динамические маршруты
        // Регистрируются через единый DynamicRouteRegistrar::register()
        // Деревья source-репозиториев объединяются через MergedRouteTreeService.
        // Порядок: декларативные (web_core.php → api.php → api_admin.php) → динамические из БД
        $this->registerAllRoutes();

        // 6) Fallback - строго последним
        // Обрабатывает все несовпавшие запросы (404) для ВСЕХ HTTP методов
        // ВАЖНО: Fallback НЕ должен быть под web middleware!
        // Иначе POST на несуществующий путь получит 419 CSRF вместо 404.
        // Контроллер сам определяет формат ответа (HTML/JSON) по типу запроса.
        //
        // Регистрируем fallback для каждого метода отдельно, т.к. Route::fallback()
        // по умолчанию только для GET/HEAD
        $fallbackController = FallbackController::class;
        Route::fallback($fallbackController); // GET, HEAD
        Route::match(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '{any?}', $fallbackController)
            ->where('any', '.*')
            ->fallback();

        $this->app->tag([RoutingCapabilityProvider::class], 'access.capability_providers');
    }

    /**
     * Зарегистрировать все маршруты (декларативные и динамические).
     *
     * Использует RouteRegistrar сервис для регистрации всех маршрутов.
     * Порядок регистрации: декларативные → динамические из БД.
     * При ошибке регистрации логирует, но не прерывает загрузку приложения.
     */
    private function registerAllRoutes(): void
    {
        $routeRegistrar = $this->app->make(RouteRegistrar::class);
        $routeRegistrar->registerAllRoutes();
    }
}
