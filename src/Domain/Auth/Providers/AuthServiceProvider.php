<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Auth\Application\Contracts\AuthenticationAudit;
use Polymorph\Platform\Domain\Auth\Application\Contracts\AuthenticationLock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\Clock;
use Polymorph\Platform\Domain\Auth\Application\Contracts\IdGenerator;
use Polymorph\Platform\Domain\Auth\Application\Contracts\PasswordHasher;
use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionCredentials;
use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionRepository;
use Polymorph\Platform\Domain\Auth\Application\Contracts\TransactionManager;
use Polymorph\Platform\Domain\Auth\Application\Contracts\UserGateway;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenAudit;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenAuthorizer;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenReadModel;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenScopeCatalog;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenSecretCodec;
use Polymorph\Platform\Domain\Auth\Http\Support\AuthHttpResponder;
use Polymorph\Platform\Domain\Auth\Infrastructure\Authentication\ApiGuard;
use Polymorph\Platform\Domain\Auth\Infrastructure\Authentication\PersonalAccessTokenCredentialAuthenticator;
use Polymorph\Platform\Domain\Auth\Infrastructure\Authentication\SessionCredentialAuthenticator;
use Polymorph\Platform\Domain\Auth\Infrastructure\Config\SessionCookieConfig;
use Polymorph\Platform\Domain\Auth\Infrastructure\Console\PruneAuthSessionsCommand;
use Polymorph\Platform\Domain\Auth\Infrastructure\Gateways\EloquentUserGateway;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\SessionCookie;
use Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\Authorization\RegisteredPersonalAccessTokenScopeCatalog;
use Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\Observability\LoggedPersonalAccessTokenAudit;
use Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\Persistence\EloquentPersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\ReadModel\EloquentPersonalAccessTokenReadModel;
use Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\Security\V1PersonalAccessTokenSecretCodec;
use Polymorph\Platform\Domain\Auth\Infrastructure\Repositories\EloquentSessionRepository;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session\AuthUserSessionRevoker;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session\DatabaseAuthenticationLock;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session\LaravelPasswordHasher;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session\LoggedAuthenticationAudit;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session\SecureSessionCredentials;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared\BestEffortAudit;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared\LaravelTransactionManager;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared\SystemClock;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared\UuidGenerator;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserSessionRevoker;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;

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
        $this->registerPersonalAccessTokenCapability();
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

        if ($this->app->environment('production')) {
            SessionCookieConfig::fromArray((array) config('authentication.cookies', []));
        }
    }

    private function registerSharedAdapters(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(IdGenerator::class, UuidGenerator::class);
        $this->app->singleton(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->singleton(BestEffortAudit::class);
        $this->app->singleton(UserGateway::class, EloquentUserGateway::class);
    }

    private function registerSessionCapability(): void
    {
        $this->app->singleton(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->singleton(SessionCredentials::class, SecureSessionCredentials::class);
        $this->app->singleton(AuthenticationLock::class, DatabaseAuthenticationLock::class);
        $this->app->singleton(SessionRepository::class, EloquentSessionRepository::class);
        $this->app->singleton(AuthenticationAudit::class, LoggedAuthenticationAudit::class);
        $this->app->singleton(UserSessionRevoker::class, AuthUserSessionRevoker::class);
    }

    private function registerPersonalAccessTokenCapability(): void
    {
        $this->app->singleton(PersonalAccessTokenRepository::class, EloquentPersonalAccessTokenRepository::class);
        $this->app->singleton(PersonalAccessTokenSecretCodec::class, V1PersonalAccessTokenSecretCodec::class);
        $this->app->singleton(PersonalAccessTokenReadModel::class, EloquentPersonalAccessTokenReadModel::class);
        $this->app->singleton(PersonalAccessTokenAudit::class, LoggedPersonalAccessTokenAudit::class);
        $this->app->scoped(PersonalAccessTokenAuthorizer::class);
        $this->app->scoped(PersonalAccessTokenScopeCatalog::class, RegisteredPersonalAccessTokenScopeCatalog::class);
    }

    private function registerRequestRuntime(): void
    {
        $this->app->scoped(AuthHttpResponder::class);

        $this->app->singleton(SessionCookie::class);
        $this->app->scoped(SessionCredentialAuthenticator::class);
        $this->app->scoped(PersonalAccessTokenCredentialAuthenticator::class);
        $this->app->scoped(AuthenticationContext::class);
    }

    private function registerSessionPruning(): void
    {
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command(PruneAuthSessionsCommand::class)->daily();
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
        Auth::extend('personal-access-token', static function ($app): ApiGuard {
            $guard = new ApiGuard(
                $app->make('request'),
                $app->make(AuthenticationContext::class),
                $app->make(PersonalAccessTokenCredentialAuthenticator::class),
            );
            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }
}
