<?php

declare(strict_types=1);

namespace Polymorph\Platform\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Extensions\Http\ExtensionErrorResolver;
use Polymorph\Platform\Support\Errors\ErrorCatalog;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorKernel;
use Polymorph\Platform\Support\Errors\ErrorReporter;
use Polymorph\Platform\Support\Errors\ErrorReportPolicy;
use Polymorph\Platform\Support\Errors\Resolvers\ErrorConvertibleResolver;
use Polymorph\Platform\Support\Errors\Resolvers\FrameworkErrorResolver;
use Polymorph\Platform\Support\Errors\Resolvers\PipelineFailureResolver;
use Polymorph\Platform\Support\Logging\TraceId;

/**
 * Основной Service Provider приложения.
 *
 * Регистрирует общие сервисы приложения:
 * - ErrorKernel Рё ErrorFactory (singleton)
 *
 * Примечание: Доменные зависимости регистрируются в соответствующих
 * Domain Service Providers (AuthServiceProvider, RoutingServiceProvider и т.д.)
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрировать сервисы приложения.
     *
     * Регистрирует общие сервисы как singleton.
     */
    public function register(): void
    {
        // Правила валидации — собственность ядра (config/validation_constraints.php).
        // Ядро использует Polymorph\Platform\Support\Validation\ValidationConstraints напрямую;
        // плагинам та же поверхность отдаётся по DI через V2 SDK-контракт
        // (Polymorph\Sdk\Validation\ValidationConstraints → Extensions\SdkBridge\SdkValidationConstraints).
        // Никакой статической проекции в SDK.

        // Каталог типов ошибок — обычный конфиг: файл стал чистыми данными, поэтому
        // мёржится как остальные и приложение остаётся config-cacheable.
        //
        // ВНИМАНИЕ: mergeConfigFrom мёржит плоско, по верхнему ключу. Хост, объявивший
        // свой config/errors.php с ключом types, ЗАМЕНИТ каталог целиком, а не дополнит
        // его — и ErrorCatalog::get() упадёт на первом же не объявленном коде, то есть
        // внутри обработчика ошибок. Переопределять тип можно только вместе со всем
        // каталогом; точечное переопределение потребовало бы мёржа по errors.types.*.
        $this->app->singleton(ErrorCatalog::class, static function ($app): ErrorCatalog {
            /** @var array<string, array{uri: string, title: string, status: int, detail: string}> $types */
            $types = $app->make('config')->get('errors.types', []);

            return ErrorCatalog::fromConfig($types);
        });

        $this->app->singleton(ErrorFactory::class);

        // Порядок резолверов — единственное место, где он задан, и он значим:
        // 1. свои исключения (включая HttpErrorException) описывают себя сами;
        // 2. ошибки SDK расширений — до маппера, иначе его ветвь RuntimeException
        //    превратила бы их в общий 500;
        // 3. доменный сбой пайплайна разворачивается штатным маппером стадий;
        // 4. фреймворк и SPL — последними, потому что RuntimeException там предок почти всего.
        //
        // Scoped, а НЕ singleton: ядро держит ErrorReporter, а тот — scoped TraceId.
        // Singleton захватил бы его на первом запросе и раздавал бы дальше, то есть
        // молча обнулил бы scoped-время жизни идентификатора: под Octane все записи в
        // логе получили бы trace_id первого запроса воркера, тогда как в тело ответа
        // уезжал бы правильный. Внутри одного запроса scoped ведёт себя как singleton.
        $this->app->scoped(ErrorKernel::class, static fn ($app): ErrorKernel => new ErrorKernel(
            $app->make(ErrorFactory::class),
            $app->make(ErrorReportPolicy::class),
            $app->make(ErrorReporter::class),
            $app->make(ErrorConvertibleResolver::class),
            $app->make(ExtensionErrorResolver::class),
            $app->make(PipelineFailureResolver::class),
            $app->make(FrameworkErrorResolver::class),
        ));

        // Scoped, не singleton: id живёт ровно один запрос, иначе под Octane все
        // запросы воркера получили бы один и тот же trace_id.
        $this->app->scoped(TraceId::class, static function ($app): TraceId {
            $request = $app->bound('request') ? $app->make('request') : null;

            return new TraceId($request instanceof Request ? $request : null);
        });
    }

    public function boot(): void
    {
        RateLimiter::for('auth-login', static function (Request $request): Limit {
            $input = $request->input('email');
            $email = is_string($input) ? strtolower(trim($input)) : '';
            $key = sha1($request->ip().'|'.$email);

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('auth-verify-resend', static function (Request $request): Limit {
            $userId = optional($request->user())->getAuthIdentifier();
            $key = $userId !== null ? 'user:'.$userId : 'ip:'.$request->ip();

            return Limit::perMinute(3)->by((string) $key);
        });
    }
}
