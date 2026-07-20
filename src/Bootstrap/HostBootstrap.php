<?php

declare(strict_types=1);

namespace Polymorph\Platform\Bootstrap;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Polymorph\Platform\Domain\AccessControl\Console\RebuildAccessControlCommand;
use Polymorph\Platform\Domain\Auth\Console\PruneAuthSessionsCommand;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\RequestCredentialAuthenticator;
use Polymorph\Platform\Domain\Extensions\Console\MigrateLegacyExtensionDataCommand;
use Polymorph\Platform\Domain\Extensions\Console\PluginsBuildCommand;
use Polymorph\Platform\Domain\Extensions\Console\PluginsDeployCommand;
use Polymorph\Platform\Domain\Extensions\Console\PluginsDisableCommand;
use Polymorph\Platform\Domain\Extensions\Console\PluginsEnableCommand;
use Polymorph\Platform\Domain\Extensions\Console\PluginsInstallCommand;
use Polymorph\Platform\Domain\Extensions\Console\PluginsListCommand;
use Polymorph\Platform\Domain\Extensions\Console\PluginsUninstallCommand;
use Polymorph\Platform\Domain\Extensions\Console\PluginsUpdateCommand;
use Polymorph\Platform\Domain\Extensions\Console\ScaffoldPluginCommand;
use Polymorph\Platform\Domain\Extensions\Console\SyncTrustedExtensionsCommand;
use Polymorph\Platform\Domain\Extensions\Http\ReplyRenderer;
use Polymorph\Platform\Domain\Routing\Console\CacheRoutesCommand;
use Polymorph\Platform\Domain\Routing\Console\ClearRouteCacheCommand;
use Polymorph\Platform\Domain\Routing\Console\LintRoutesCommand;
use Polymorph\Platform\Http\Middleware\AddCacheVary;
use Polymorph\Platform\Http\Middleware\CanonicalUrl;
use Polymorph\Platform\Http\Middleware\EnsureSessionCredential;
use Polymorph\Platform\Http\Middleware\NoCacheAuth;
use Polymorph\Platform\Http\Middleware\OptionalApiAuth;
use Polymorph\Platform\Http\Middleware\RenewAccessTokenCookie;
use Polymorph\Platform\Http\Middleware\RequireCapability;
use Polymorph\Platform\Http\Middleware\VerifyApiCsrf;
use Polymorph\Platform\PipelineCore\Runtime\PipelineDomainFailureException;
use Polymorph\Platform\Support\Console\PreflightCommand;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorKernel;
use Polymorph\Platform\Support\Errors\ErrorReporter;
use Polymorph\Platform\Support\Errors\ErrorResponseFactory;
use Polymorph\Platform\Support\Errors\HttpErrorException;
use Polymorph\Platform\Support\Errors\PipelineDomainFailureHttpMapper;
use Polymorph\Sdk\Errors\ExtensionError;
use Throwable;

/**
 * Application-level (bootstrap/app.php) configuration that a ServiceProvider cannot reach:
 * middleware/exceptions/commands are wired in Laravel 12's Application::configure(). The thin
 * host delegates to these static configurators (ADR 0006 §4.7). A single class avoids the
 * name collision between a `Http\Middleware` configurator and the `Http\Middleware\` namespace.
 */
final class HostBootstrap
{
    /** @return array<int, class-string> */
    public static function commands(): array
    {
        return [
            PreflightCommand::class,
            RebuildAccessControlCommand::class,
            CacheRoutesCommand::class,
            ClearRouteCacheCommand::class,
            LintRoutesCommand::class,
            PruneAuthSessionsCommand::class,
            PluginsListCommand::class,
            PluginsInstallCommand::class,
            PluginsUninstallCommand::class,
            PluginsEnableCommand::class,
            PluginsDisableCommand::class,
            PluginsUpdateCommand::class,
            PluginsBuildCommand::class,
            PluginsDeployCommand::class,
            ScaffoldPluginCommand::class,
            MigrateLegacyExtensionDataCommand::class,
            SyncTrustedExtensionsCommand::class,
        ];
    }

    public static function middleware(Middleware $middleware): void
    {
        // This runs before Laravel has bootstrapped the config repository.
        $jwtAccessCookie = (string) env('JWT_ACCESS_COOKIE', 'cms_at');
        $jwtRefreshCookie = (string) env('JWT_REFRESH_COOKIE', 'cms_rt');

        $middleware->encryptCookies(except: array_values(array_unique(array_filter([
            $jwtAccessCookie,
            $jwtRefreshCookie,
        ], static fn ($name): bool => is_string($name) && trim($name) !== ''))));

        // Canonicalisation applies globally to all HTTP requests (redirect /About -> /about
        // before routing). The middleware itself filters system paths (admin, api, auth, ...).
        $middleware->prepend(CanonicalUrl::class);

        // API group order: CORS -> CSRF -> Vary -> Auth.
        $middleware->appendToGroup('api', VerifyApiCsrf::class);
        $middleware->appendToGroup('api', AddCacheVary::class);
        $middleware->appendToGroup('api', RenewAccessTokenCookie::class);

        $middleware->alias([
            OptionalApiAuth::ALIAS => OptionalApiAuth::class,
            'session.credential' => EnsureSessionCredential::class,
            'no-cache-auth' => NoCacheAuth::class,
            RequireCapability::ALIAS => RequireCapability::class,
        ]);
    }

    public static function exceptions(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e, $request) {
            // Extension SDK V2 errors -> always the core ProblemJson, even outside api/*.
            if ($e instanceof ExtensionError) {
                $payload = app(ReplyRenderer::class)->toPayload($e);
                ErrorReporter::report($e, $payload, null);

                $response = ErrorResponseFactory::make($payload);
                if ($payload->status === 401) {
                    $response->headers->set('WWW-Authenticate', 'Bearer');
                }
                $retryAfter = $e->meta['retry_after'] ?? null;
                if (is_int($retryAfter) || (is_string($retryAfter) && $retryAfter !== '')) {
                    $response->headers->set('Retry-After', (string) $retryAfter);
                }

                return $response;
            }

            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            if ($e instanceof PipelineDomainFailureException) {
                /** @var PipelineDomainFailureHttpMapper $mapper */
                $mapper = app(PipelineDomainFailureHttpMapper::class);
                $e = $mapper->map($e);
            }

            if ($e instanceof HttpErrorException) {
                ErrorReporter::report($e, $e->payload(), null);

                $response = ErrorResponseFactory::make($e->payload());

                return $e->apply($response);
            }

            /** @var ErrorKernel $kernel */
            $kernel = app(ErrorKernel::class);

            if ($e instanceof AuthenticationException) {
                $reason = (string) $request->attributes->get(
                    RequestCredentialAuthenticator::FAILURE_REASON_ATTRIBUTE,
                    'missing_token',
                );
                $message = (string) $request->attributes->get(
                    RequestCredentialAuthenticator::FAILURE_MESSAGE_ATTRIBUTE,
                    RequestCredentialAuthenticator::failureMessage($reason),
                );

                $payload = $kernel->factory()
                    ->for(ErrorCode::UNAUTHORIZED)
                    ->meta([
                        'reason' => $reason,
                        'message' => $message,
                    ])
                    ->build();
                ErrorReporter::report($e, $payload, null);

                $response = ErrorResponseFactory::make($payload);
                $response->headers->set('WWW-Authenticate', 'Bearer');
                $response->headers->set('Pragma', 'no-cache');

                return $response;
            }

            $payload = $kernel->resolve($e);

            return ErrorResponseFactory::make($payload);
        });
    }
}
