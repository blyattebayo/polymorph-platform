<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Auth\Application\Support\UserCapabilitiesPresenter;
use Polymorph\Platform\Domain\Auth\Console\PruneAuthSessionsCommand;
use Polymorph\Platform\Domain\Auth\Core\Contracts\CredentialAuthenticator;
use Polymorph\Platform\Domain\Auth\Core\Contracts\EmailVerificationNotifier;
use Polymorph\Platform\Domain\Auth\Core\Contracts\PersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Core\Contracts\RefreshSessionRepository;
use Polymorph\Platform\Domain\Auth\Core\Exceptions\JwtConfigurationException;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthCookieConfig;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthSessionConfig;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\JwtConfig;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\PatConfig;
use Polymorph\Platform\Domain\Auth\Events\PersonalAccessTokenCreated;
use Polymorph\Platform\Domain\Auth\Events\PersonalAccessTokenRevoked;
use Polymorph\Platform\Domain\Auth\Events\UserLoggedIn;
use Polymorph\Platform\Domain\Auth\Events\UserLoggedOut;
use Polymorph\Platform\Domain\Auth\Infrastructure\Guard\ApiGuard;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\PresentedTokenExtractor;
use Polymorph\Platform\Domain\Auth\Infrastructure\Notifications\LaravelEmailVerificationNotifier;
use Polymorph\Platform\Domain\Auth\Infrastructure\Repositories\EloquentPersonalAccessTokenRepository;
use Polymorph\Platform\Domain\Auth\Infrastructure\Repositories\EloquentRefreshSessionRepository;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\AccessTokenDenylist;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\CredentialAuthenticatorRegistry;
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

        // Единственное место, где читается config('jwt.*') и config('pat.*').
        // Замыкания ленивые: снимок конфига делается при первом обращении, а не
        // на регистрации, — иначе тесты, подменяющие секрет и TTL в beforeEach,
        // получали бы значения, снятые до подмены.
        $this->app->singleton(JwtConfig::class, static fn (): JwtConfig => JwtConfig::fromArray((array) config('jwt', [])));
        $this->app->singleton(AuthSessionConfig::class, static fn (): AuthSessionConfig => AuthSessionConfig::fromArray((array) config('jwt', [])));
        $this->app->singleton(PatConfig::class, static fn (): PatConfig => PatConfig::fromArray((array) config('pat', [])));
        $this->app->singleton(AuthCookieConfig::class, static fn (): AuthCookieConfig => AuthCookieConfig::fromArray(
            (array) config('jwt.cookies', []),
            secureByDefault: config('app.env') !== 'local',
        ));

        $this->app->singleton(JwtService::class);

        $this->app->singleton(PersonalAccessTokenRepository::class, EloquentPersonalAccessTokenRepository::class);
        $this->app->singleton(PersonalAccessTokenService::class);
        $this->app->scoped(UserCapabilitiesPresenter::class);
        $this->app->singleton(PresentedTokenExtractor::class);
        $this->app->singleton(JwtCredentialAuthenticator::class);
        $this->app->singleton(PatCredentialAuthenticator::class);

        // Порядок реестра = порядок этого списка: побеждает первый способ,
        // чей supports() опознал токен. Третий способ добавляется сюда (или
        // тегом из провайдера расширения), а не правкой условия в диспетчере.
        $this->app->tag([
            PatCredentialAuthenticator::class,
            JwtCredentialAuthenticator::class,
        ], CredentialAuthenticator::TAG);

        $this->app->singleton(
            CredentialAuthenticatorRegistry::class,
            static fn ($app): CredentialAuthenticatorRegistry => new CredentialAuthenticatorRegistry(
                $app->tagged(CredentialAuthenticator::TAG),
            ),
        );

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
        // JWT::$leeway ставит сам JwtService из своего JwtConfig: здесь пришлось
        // бы резолвить конфиг на boot, то есть снимать его до того, как код
        // (и тесты) успеют его задать.
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

    /**
     * Читает config напрямую намеренно: это проверка на регистрации, до того
     * как хоть кто-то успел резолвить JwtConfig. Резолвить его здесь значило бы
     * зафиксировать снимок конфига на этапе загрузки провайдеров.
     */
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
