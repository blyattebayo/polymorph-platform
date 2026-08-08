<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Providers;

use Firebase\JWT\JWT;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Auth\Application\Support\UserCapabilitiesPresenter;
use Polymorph\Platform\Domain\Auth\Console\PruneAuthSessionsCommand;
use Polymorph\Platform\Domain\Auth\Core\Contracts\EmailVerificationNotifier;
use Polymorph\Platform\Domain\Auth\Core\Contracts\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Core\Contracts\RefreshSessionRepository;
use Polymorph\Platform\Domain\Auth\Core\Exceptions\JwtConfigurationException;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\JwtConfig;
use Polymorph\Platform\Domain\Auth\Events\PersonalAccessTokenCreated;
use Polymorph\Platform\Domain\Auth\Events\PersonalAccessTokenRevoked;
use Polymorph\Platform\Domain\Auth\Events\UserLoggedIn;
use Polymorph\Platform\Domain\Auth\Events\UserLoggedOut;
use Polymorph\Platform\Domain\Auth\Infrastructure\Guard\ApiGuard;
use Polymorph\Platform\Domain\Auth\Infrastructure\Notifications\LaravelEmailVerificationNotifier;
use Polymorph\Platform\Domain\Auth\Infrastructure\Repositories\EloquentPersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Infrastructure\Repositories\EloquentRefreshSessionRepository;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\AccessTokenDenylist;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\CompositeCredentialAuthenticator;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\JwtCredentialAuthenticator;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\JwtService;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\PatCredentialAuthenticator;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\PersonalAccessTokenService;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\RequestCredentialAuthenticator;
use Polymorph\Platform\Domain\Auth\Listeners\LogAuthEvent;
use Polymorph\Platform\Domain\Auth\Listeners\LogPersonalAccessTokenEvent;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserSessionRevoker;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Polymorph\Platform\SharedKernel\Identity\RequestCredentialResolver;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->assertProductionJwtSecretConfigured();

        $this->app->singleton(JwtService::class, function () {
            return new JwtService(JwtConfig::fromArray((array) config('jwt', [])));
        });

        $this->app->singleton(PersonalAccessTokenRepository::class, EloquentPersonalAccessTokenRepository::class);
        $this->app->singleton(PersonalAccessTokenService::class);
        $this->app->scoped(UserCapabilitiesPresenter::class);
        $this->app->singleton(JwtCredentialAuthenticator::class);
        $this->app->singleton(PatCredentialAuthenticator::class);
        $this->app->singleton(CompositeCredentialAuthenticator::class);
        $this->app->singleton(RequestCredentialAuthenticator::class);
        $this->app->singleton(RequestCredentialResolver::class, RequestCredentialAuthenticator::class);

        // Scoped, не singleton: контекст помнит актора, назначенного вручную
        // (Auth::setUser), и под Octane это не должно протекать между запросами.
        $this->app->scoped(AuthenticationContext::class);

        $this->app->singleton(AccessTokenDenylist::class);
        $this->app->singleton(RefreshSessionRepository::class, EloquentRefreshSessionRepository::class);
        $this->app->singleton(EmailVerificationNotifier::class, LaravelEmailVerificationNotifier::class);
        $this->app->singleton(UserSessionRevoker::class, EloquentRefreshSessionRepository::class);
    }

    public function boot(): void
    {
        JWT::$leeway = (int) config('jwt.leeway', 5);

        $this->registerApiGuard();
        $this->registerEventListeners();
        $this->registerSchedule();
        $this->registerPasswordResetUrl();
    }

    /**
     * Ссылка сброса пароля ведёт на SPA-страницу (а не на несуществующий
     * web-route password.reset). Токен и email — в query, как ждёт фронт.
     */
    private function registerPasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(static function ($notifiable, string $token): string {
            $base = (string) config('auth.password_reset.redirect_to', '/');
            $separator = str_contains($base, '?') ? '&' : '?';

            return url($base.$separator.'token='.$token
                .'&email='.urlencode((string) $notifiable->getEmailForPasswordReset()));
        });
    }

    protected function registerEventListeners(): void
    {
        Event::listen(UserLoggedIn::class, [LogAuthEvent::class, 'handleUserLoggedIn']);
        Event::listen(UserLoggedOut::class, [LogAuthEvent::class, 'handleUserLoggedOut']);
        Event::listen(PersonalAccessTokenCreated::class, [LogPersonalAccessTokenEvent::class, 'handleCreated']);
        Event::listen(PersonalAccessTokenRevoked::class, [LogPersonalAccessTokenEvent::class, 'handleRevoked']);
    }

    private function registerApiGuard(): void
    {
        // Гард не держит ни пользователя, ни запрос, поэтому ему не нужен
        // $app->refresh('request', ...): за текущим запросом следит контекст.
        Auth::extend('api', static fn ($app): ApiGuard => new ApiGuard(
            $app->make(AuthenticationContext::class),
        ));
    }

    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command(PruneAuthSessionsCommand::class)->daily();
        });
    }

    private function assertProductionJwtSecretConfigured(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $secret = config('jwt.secret');
        if (! is_string($secret) || trim($secret) === '') {
            throw JwtConfigurationException::missingSecret();
        }
    }
}
