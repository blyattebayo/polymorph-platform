<?php

declare(strict_types=1);

namespace Polymorph\Platform\Providers;

use Polymorph\Platform\SharedKernel\Identity\CurrentActorResolver;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorKernel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Основной Service Provider приложения.
 *
 * Регистрирует общие сервисы приложения:
 * - ErrorKernel Рё ErrorFactory (singleton)
 *
 * Примечание: Доменные зависимости регистрируются в соответствующих
 * Domain Service Providers (AuthServiceProvider, RoutingServiceProvider и т.д.)
 *
 * @package Polymorph\Platform\Providers
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрировать сервисы приложения.
     *
     * Регистрирует общие сервисы как singleton.
     *
     * @return void
     */
    public function register(): void
    {
        // Правила валидации — собственность ядра (config/validation_constraints.php).
        // Ядро использует Polymorph\Platform\Support\Validation\ValidationConstraints напрямую;
        // плагинам та же поверхность отдаётся по DI через V2 SDK-контракт
        // (Polymorph\Sdk\Validation\ValidationConstraints → Extensions\SdkBridge\SdkValidationConstraints).
        // Никакой статической проекции в SDK.

        // ErrorKernel — единая точка обработки ошибок API.
        // config/errors.php хранит Closure-билдеры как значения, поэтому его НЕ мёржат
        // в config() (иначе config:cache/optimize падают на несериализуемом Closure —
        // см. PlatformServiceProvider::CONFIG_KEYS). Грузим файл напрямую: массив тот же,
        // но замыкания не попадают в кэшируемый config-репозиторий.
        $this->app->singleton(ErrorKernel::class, function ($app) {
            /** @var array<string, mixed> $config */
            $config = require dirname(__DIR__, 2) . '/config/errors.php';

            return ErrorKernel::fromConfig($config, $app);
        });

        $this->app->singleton(ErrorFactory::class, static fn ($app): ErrorFactory => $app->make(ErrorKernel::class)->factory());
        $this->app->scoped(UserIdentity::class, static fn ($app): UserIdentity => $app->make(CurrentActorResolver::class)->requireActor());

    }

    public function boot(): void
    {
        RateLimiter::for('auth-login', static function (Request $request): Limit {
            $email = strtolower(trim((string) $request->input('email', '')));
            $key = sha1($request->ip() . '|' . $email);

            return Limit::perMinute(5)->by($key)->response(static function () {
                return response()->json([
                    'code' => 'RATE_LIMITED',
                    'message' => 'Too many login attempts.',
                ], 429)->withHeaders(['Retry-After' => '60']);
            });
        });

        RateLimiter::for('auth-refresh', static function (Request $request): Limit {
            $refreshCookieName = (string) config('jwt.cookies.refresh', 'cms_rt');
            $fingerprint = hash('sha256', (string) $request->cookie($refreshCookieName, ''));
            $key = sha1($request->ip() . '|' . $fingerprint);

            return Limit::perMinute(20)->by($key);
        });

        RateLimiter::for('pat-create', static function (Request $request): Limit {
            $userId = optional($request->user())->getAuthIdentifier();
            $key = $userId !== null ? 'user:' . $userId : 'ip:' . $request->ip();

            return Limit::perMinute(10)->by((string) $key);
        });

        RateLimiter::for('auth-password-reset', static function (Request $request): Limit {
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinute(3)->by(sha1($request->ip() . '|' . $email));
        });

        RateLimiter::for('auth-register', static function (Request $request): Limit {
            $email = strtolower(trim((string) $request->input('email', '')));
            $key = sha1($request->ip() . '|' . $email);

            return Limit::perMinute(5)->by($key)->response(static function () {
                return response()->json([
                    'code' => 'RATE_LIMITED',
                    'message' => 'Too many registration attempts.',
                ], 429)->withHeaders(['Retry-After' => '60']);
            });
        });

        RateLimiter::for('auth-verify-resend', static function (Request $request): Limit {
            $userId = optional($request->user())->getAuthIdentifier();
            $key = $userId !== null ? 'user:' . $userId : 'ip:' . $request->ip();

            return Limit::perMinute(3)->by((string) $key);
        });
    }

}
