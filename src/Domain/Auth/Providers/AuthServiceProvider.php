<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\Auth\Application\Contracts\AuthenticationAudit;
use Polymorph\Platform\Domain\Auth\Application\Contracts\AuthenticationLock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\Clock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\IdGenerator;
use Polymorph\Platform\Domain\Auth\Application\Contracts\PasswordHasher;
use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionCredentials;
use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionRepository;
use Polymorph\Platform\Domain\Auth\Application\Contracts\TransactionManager;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthAuthorizationServer;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthSecrets;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthServerConfig;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthStore;
use Polymorph\Platform\Domain\Auth\Http\Support\AuthHttpResponder;
use Polymorph\Platform\Domain\Auth\Infrastructure\Authentication\ApiGuard;
use Polymorph\Platform\Domain\Auth\Infrastructure\Authentication\OAuthAccessTokenCredentialAuthenticator;
use Polymorph\Platform\Domain\Auth\Infrastructure\Authentication\SessionCredentialAuthenticator;
use Polymorph\Platform\Domain\Auth\Infrastructure\Config\SessionCookieConfig;
use Polymorph\Platform\Domain\Auth\Infrastructure\Console\PruneAuthSessionsCommand;
use Polymorph\Platform\Domain\Auth\Infrastructure\Console\PruneOAuthCredentialsCommand;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\SessionCookie;
use Polymorph\Platform\Domain\Auth\Infrastructure\OAuth\DatabaseOAuthStore;
use Polymorph\Platform\Domain\Auth\Infrastructure\OAuth\SecureOAuthSecrets;
use Polymorph\Platform\Domain\Auth\Infrastructure\Repositories\EloquentSessionRepository;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session\DatabaseAuthenticationLock;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session\DatabaseUserSessionRevoker;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session\LaravelPasswordHasher;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session\LoggedAuthenticationAudit;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session\SecureSessionCredentials;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared\BestEffortAudit;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared\LaravelTransactionManager;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared\SystemClock;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared\UuidGenerator;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserSessionRevoker;

/**
 * Complete composition root for the platform's sole interactive-auth runtime.
 */
final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfiguration();
        $this->registerSharedAdapters();
        $this->registerSessionCapability();
        $this->registerOAuthCapability();
        $this->registerRequestRuntime();
    }

    public function boot(): void
    {
        $this->registerSessionPruning();
        $this->registerApiGuard();
    }

    private function registerConfiguration(): void
    {
        $this->app->singleton(SessionCookieConfig::class, static fn (): SessionCookieConfig => SessionCookieConfig::fromArray(
            (array) config('authentication.cookies', []),
        ));
        $this->app->singleton(OAuthServerConfig::class, fn (): OAuthServerConfig => OAuthServerConfig::fromApplicationUrl(
            $this->oauthPublicUrl(),
            $this->app->environment('production'),
        ));

        if ($this->app->environment('production')) {
            SessionCookieConfig::fromArray((array) config('authentication.cookies', []));
            OAuthServerConfig::fromApplicationUrl(
                $this->oauthPublicUrl(),
                production: true,
            );
        }
    }

    private function oauthPublicUrl(): string
    {
        $publicUrl = config('authentication.oauth.public_url');

        return is_string($publicUrl) && trim($publicUrl) !== ''
            ? $publicUrl
            : (string) config('app.url');
    }

    private function registerSharedAdapters(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(IdGenerator::class, UuidGenerator::class);
        $this->app->singleton(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->singleton(BestEffortAudit::class);
    }

    private function registerSessionCapability(): void
    {
        $this->app->singleton(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->singleton(SessionCredentials::class, SecureSessionCredentials::class);
        $this->app->singleton(AuthenticationLock::class, DatabaseAuthenticationLock::class);
        $this->app->singleton(SessionRepository::class, EloquentSessionRepository::class);
        $this->app->singleton(AuthenticationAudit::class, LoggedAuthenticationAudit::class);
        $this->app->singleton(UserSessionRevoker::class, DatabaseUserSessionRevoker::class);
    }

    private function registerOAuthCapability(): void
    {
        $this->app->singleton(OAuthStore::class, DatabaseOAuthStore::class);
        $this->app->singleton(OAuthSecrets::class, SecureOAuthSecrets::class);
        $this->app->scoped(OAuthAuthorizationServer::class);
    }

    private function registerRequestRuntime(): void
    {
        $this->app->scoped(AuthHttpResponder::class);

        $this->app->singleton(SessionCookie::class);
        $this->app->scoped(SessionCredentialAuthenticator::class);
        $this->app->scoped(OAuthAccessTokenCredentialAuthenticator::class);
        $this->app->scoped(AuthenticationContext::class);
    }

    private function registerSessionPruning(): void
    {
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command(PruneAuthSessionsCommand::class)->daily();
            $schedule->command(PruneOAuthCredentialsCommand::class)->daily();
        });
    }

    private function registerApiGuard(): void
    {
        Auth::extend('session-cookie', static function ($app): ApiGuard {
            $guard = new ApiGuard(
                $app->make('request'),
                $app->make(AuthenticationContext::class),
                $app->make(SessionCredentialAuthenticator::class),
            );
            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }
}
