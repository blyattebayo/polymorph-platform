<?php

declare(strict_types=1);

namespace Polymorph\Platform\Bootstrap;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\AccessControl\Console\GenerateFeCapabilityCatalogCommand;
use Polymorph\Platform\Domain\AccessControl\Console\RebuildAccessControlCommand;
use Polymorph\Platform\Domain\Auth\Infrastructure\Console\PruneAuthSessionsCommand;
use Polymorph\Platform\Domain\Auth\Infrastructure\Console\PruneOAuthCredentialsCommand;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\SessionCookie;
use Polymorph\Platform\Domain\Extensions\Console\PluginsBuildCommand;
use Polymorph\Platform\Domain\Extensions\Console\PluginsInstallCommand;
use Polymorph\Platform\Domain\Extensions\Console\PluginsListCommand;
use Polymorph\Platform\Http\ApiErrorHandler;
use Polymorph\Platform\Http\Middleware\AddCacheVary;
use Polymorph\Platform\Http\Middleware\AuthenticateOAuthResource;
use Polymorph\Platform\Http\Middleware\CanonicalUrl;
use Polymorph\Platform\Http\Middleware\NoCacheAuth;
use Polymorph\Platform\Http\Middleware\RequireCapability;
use Polymorph\Platform\Http\Middleware\ResolveSessionCredential;
use Polymorph\Platform\Http\Middleware\VerifyApiCsrf;
use Polymorph\Platform\Support\Console\PreflightCommand;
use Throwable;

/**
 * Application-level (bootstrap/app.php) configuration that a ServiceProvider cannot reach:
 * middleware/exceptions/commands are wired in Laravel 12's Application::configure(). The thin
 * host delegates to these static configurators. A single class avoids the
 * name collision between a `Http\Middleware` configurator and the `Http\Middleware\` namespace.
 */
final class HostBootstrap
{
    /**
     * Консольные команды хоста.
     *
     * Команды маршрутизации сюда НЕ входят: они свои у каждого движка и
     * регистрируются его провайдером. Иначе команды v1 (routing:lint,
     * route:cache-db) существовали бы и под v2 — и молча затеняли бы
     * одноимённые команды работающего движка.
     *
     * @return array<int, class-string>
     */
    public static function commands(): array
    {
        return [
            PreflightCommand::class,
            RebuildAccessControlCommand::class,
            GenerateFeCapabilityCatalogCommand::class,
            PruneAuthSessionsCommand::class,
            PruneOAuthCredentialsCommand::class,
            PluginsListCommand::class,
            PluginsInstallCommand::class,
            PluginsBuildCommand::class,
        ];
    }

    public static function middleware(Middleware $middleware): void
    {
        // This product has no server-rendered login route. Every protected API request
        // has the same 401 contract, regardless of content-negotiation headers.
        $middleware->redirectGuestsTo(static fn (Request $request): ?string => null);

        $middleware->encryptCookies(except: [SessionCookie::NAME]);

        // Canonicalisation applies globally to all HTTP requests (redirect /About -> /about
        // before routing). The middleware itself filters system paths (admin, api, auth, ...).
        $middleware->prepend(CanonicalUrl::class);

        // API group order: CORS -> CSRF -> Vary -> Auth.
        $middleware->appendToGroup('api', VerifyApiCsrf::class);
        $middleware->appendToGroup('api', AddCacheVary::class);

        $middleware->alias([
            ResolveSessionCredential::ALIAS => ResolveSessionCredential::class,
            AuthenticateOAuthResource::ALIAS => AuthenticateOAuthResource::class,
            'no-cache-auth' => NoCacheAuth::class,
            RequireCapability::ALIAS => RequireCapability::class,
        ]);
    }

    /**
     * Hook фреймворка только делегирует: вся обработка — в {@see ApiErrorHandler},
     * который можно позвать из теста без поднятого bootstrap'а. Почему обработка
     * выглядит именно так — в докблоке этого класса, здесь этому знанию не место.
     */
    public static function exceptions(Exceptions $exceptions): void
    {
        $exceptions->render(
            static fn (Throwable $e, Request $request) => app(ApiErrorHandler::class)->handle($e, $request),
        );
    }
}
