<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\Extensions\Core\Contracts\ExtensionRoutes;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionRouteService;
use Polymorph\Platform\Domain\Routing\Access\RoutingCapabilityProvider;
use Polymorph\Platform\Domain\Routing\Console\CacheRoutesCommand;
use Polymorph\Platform\Domain\Routing\Console\ClearRouteCacheCommand;
use Polymorph\Platform\Domain\Routing\Console\LintRoutesCommand;
use Polymorph\Platform\Domain\Routing\Core\Contracts\PluginRouteCatalog;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;
use Polymorph\Platform\Domain\Routing\Events\RouteNodeCreated;
use Polymorph\Platform\Domain\Routing\Events\RouteNodeDeleted;
use Polymorph\Platform\Domain\Routing\Events\RouteNodeUpdated;
use Polymorph\Platform\Domain\Routing\Http\Controllers\FallbackController;
use Polymorph\Platform\Domain\Routing\Infrastructure\Loaders\PluginRouteLoader;
use Polymorph\Platform\Domain\Routing\Infrastructure\Loaders\RouteDefinitionLoader;
use Polymorph\Platform\Domain\Routing\Infrastructure\Loaders\SystemRouteLoader;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\ClientRouteRepository;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\PluginRouteRepository;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\SystemRouteRepository;
use Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers\RouteActionResolver;
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
        // 0. Консольные команды движка. Живут здесь, а не в HostBootstrap:
        // они управляют кешем route_nodes и линтуют дерево узлов, то есть
        // осмысленны только пока работает этот движок.

        $this->commands([
            CacheRoutesCommand::class,
            ClearRouteCacheCommand::class,
            LintRoutesCommand::class,
        ]);

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

        // 5. Резолвер действий маршрутов (stateless, зависимости автовайрятся)

        $this->app->singleton(RouteActionResolver::class);

        // 6. Загрузчики маршрутов

        $this->app->singleton(RouteDefinitionLoader::class, function ($app) {
            return new RouteDefinitionLoader(
                $app->make(RouteValidator::class),
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

        // 7. Репозитории

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

        // 8. Сервисы высокого уровня

        $this->app->singleton(MergedRouteTreeService::class, function ($app) {
            // Список источников дерева (RouteTreeSource). Порядок не влияет на
            // итог (сортировка по приоритету владельца), задан для читаемости.
            return new MergedRouteTreeService([
                $app->make(SystemRouteRepository::class),
                $app->make(PluginRouteRepository::class),
                $app->make(ClientRouteRepository::class),
            ]);
        });

        // RouteRegistrar собирается автовайрингом: все три его зависимости
        // (MergedRouteTreeService, RouteActionResolver, AppLogger) — синглтоны.
        $this->app->singleton(RouteRegistrar::class);

        // 9. Интерфейсы

        $this->app->singleton(
            RouteNodeServiceInterface::class,
            RouteNodeService::class
        );

        // Маршруты расширений в их жизненном цикле — реализация этого движка.
        // Биндится здесь, а не в ExtensionsServiceProvider: контракт один,
        // и выбирать реализацию должен тот, кто знает, какой движок работает.
        $this->app->singleton(
            ExtensionRoutes::class,
            ExtensionRouteService::class,
        );
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

        // Если маршруты закэшированы (php artisan route:cache), Laravel поднимает
        // их из файла кэша сам. Повторная регистрация продублировала бы дерево,
        // поэтому весь блок ниже пропускается.
        //
        // ВАЖНО: route:cache замораживает и клиентские маршруты из route_nodes —
        // после правок в админке нужен route:clear. См. docs/adr/0005.
        if ($this->app->routesAreCached()) {
            $this->app->tag([RoutingCapabilityProvider::class], 'access.capability_providers');

            return;
        }

        // 1-5) Декларативные и динамические маршруты
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
     * Порядок регистрации: декларативные → динамические из БД.
     *
     * При ошибке логирует и продолжает загрузку приложения: сборка дерева
     * читает БД и файлы плагинов, и единственная битая запись не должна делать
     * недоступным всё приложение, включая админку, из которой её чинят.
     * Fallback регистрируется вызывающим в любом случае, поэтому непокрытые
     * пути отдают структурированный 404, а не пустой роутер.
     */
    private function registerAllRoutes(): void
    {
        try {
            $this->app->make(RouteRegistrar::class)->registerAllRoutes();
        } catch (\Throwable $exception) {
            $this->app->make(AppLogger::class)->error('routing.registration_failed', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
