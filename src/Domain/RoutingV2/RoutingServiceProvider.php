<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RoutingV2;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Extensions\Core\Contracts\ExtensionRoutes;
use Polymorph\Platform\Domain\Extensions\Routing\ExtensionRouteMounter;
use Polymorph\Platform\Domain\RoutingV2\Console\LintRoutesCommand;
use Polymorph\Platform\Domain\RoutingV2\Http\FallbackController;
use Polymorph\Platform\Domain\RoutingV2\Plugin\PluginRouteMounter;

/**
 * Маршрутизация: ядро — нативные роут-файлы, плагины — объекты SDK.
 *
 * Весь порядок регистрации виден в boot() сверху вниз и больше нигде.
 * У маршрута нет ни owner, ни sort_order, ни enabled: приоритет задаёт
 * позиция в списке, а «выключенного» маршрута не существует — не объявляй,
 * и его не будет.
 *
 * Провайдер регистрируется РАНЬШЕ AdminServiceProvider (см. порядок в
 * PlatformServiceProvider), поэтому catch-all админки не затеняет маршруты
 * ядра. По той же причине загрузка живёт здесь, а не в withRouting(then:)
 * хоста: колбэк `then` исполняется в booted-фазе, уже ПОСЛЕ boot() всех
 * провайдеров, и порядок бы перевернулся.
 */
final class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Файлы маршрутов ядра в порядке регистрации.
     *
     * Порядок значим: для ПЕРЕСЕКАЮЩИХСЯ паттернов Laravel оставляет первый
     * подходящий, а для СОВПАДАЮЩИХ method+uri RouteCollection пишет по ключу
     * и молча оставляет последний. Второе — ошибка в любом случае, её ловит
     * routing:lint, а не порядок в этом списке.
     *
     * @var list<string>
     */
    private const CORE_FILES = [
        'web.php',
        'api.php',
        'api_plugins.php',
        'api_admin.php',
    ];

    public function register(): void
    {
        $this->commands([LintRoutesCommand::class]);

        // Маршруты расширений в их жизненном цикле — реализация этого движка.
        // Тот же PluginRouteMounter, что и на бутстрапе, поэтому «работает при
        // старте, но не работает при enable» структурно невозможно.
        $this->app->singleton(
            ExtensionRoutes::class,
            ExtensionRouteMounter::class,
        );
    }

    public function boot(): void
    {
        // Маршруты подняты из файла кеша (route:cache). Laravel заменит
        // коллекцию роутера скомпилированной УЖЕ ПОСЛЕ boot(), поэтому всё,
        // что мы здесь зарегистрируем, будет отброшено. Без этого выхода
        // остаётся только цена: require 14 файлов и запрос в БД за списком
        // включённых расширений на КАЖДОМ запросе — ровно та работа, ради
        // устранения которой кеш и делают.
        //
        // Следствие: кеш замораживает и маршруты расширений. После
        // enable/disable нужен route:clear (или повторный route:cache),
        // иначе расширение появится только в том процессе, который его включил.
        if ($this->app->routesAreCached()) {
            return;
        }

        $this->registerCoreRoutes();

        // Маршруты плагинов идут после ядра: плагин не может перехватить
        // системный путь, потому что его зона всегда лежит под ext/{id}.
        $this->app->make(PluginRouteMounter::class)->mountEnabled();

        $this->registerFallback();
    }

    /**
     * Ядро грузится БЕЗ try/catch: опечатка в файле маршрутов ядра обязана
     * ронять бутстрап громко и попадать в CI, а не превращаться в тихий 404
     * на половине приложения.
     */
    private function registerCoreRoutes(): void
    {
        $directory = rtrim((string) config('routing.v2_path', dirname(__DIR__, 3).'/routes-v2'), '/');

        foreach (self::CORE_FILES as $file) {
            require $directory.'/'.$file;
        }
    }

    /**
     * Fallback ловит ВСЕ методы и не входит в группу web: иначе POST на
     * несуществующий путь отдавал бы 419 CSRF вместо 404.
     *
     * Route::fallback() покрывает только GET/HEAD, поэтому остальные методы
     * добавляются отдельным catch-all, помеченным как fallback.
     */
    private function registerFallback(): void
    {
        Route::fallback(FallbackController::class);

        Route::match(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '{any?}', FallbackController::class)
            ->where('any', '.*')
            ->fallback();
    }
}
