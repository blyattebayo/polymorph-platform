<?php

declare(strict_types=1);

namespace Polymorph\Platform;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\AccessControl\Console\GenerateFeCapabilityCatalogCommand;
use Polymorph\Platform\Domain\Auth\Infrastructure\Console\PruneAuthSessionsCommand;
use Polymorph\Platform\Domain\Auth\Infrastructure\Console\PruneOAuthCredentialsCommand;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\SessionCookie;
use Polymorph\Platform\Domain\Extensions\Console\PluginsBuildCommand;
use Polymorph\Platform\Domain\Extensions\Console\PluginsInstallCommand;
use Polymorph\Platform\Domain\Extensions\Console\PluginsListCommand;
use Polymorph\Platform\Domain\DisplayViews\Console\RebuildRecordDefinitionDisplayViewsCommand;
use Polymorph\Platform\Domain\RecordIndexes\Console\RecordIndexesDoctorCommand;
use Polymorph\Platform\Domain\Routing\Console\LintRoutesCommand;
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
 * The platform's single composition root. A host registers only this provider; it owns
 * framework integration, domain-provider order, config, routes, commands, migrations,
 * translations and package views.
 */
final class PlatformServiceProvider extends ServiceProvider
{
    private const VIEW_PATH = __DIR__.'/../resources/views';

    /**
     * Domain providers in dependency order (mirrors the former bootstrap/providers.php).
     * Order is load-bearing: PipelineCore early; TemplateEngine precedes display-view compilation.
     *
     * @var array<int, string>
     */
    private const PROVIDERS = [
        Providers\AppServiceProvider::class,
        Support\Logging\LoggingServiceProvider::class,
        PipelineCore\Providers\PipelineCoreServiceProvider::class,
        Domain\Auth\Providers\AuthServiceProvider::class,
        Domain\Users\Providers\UsersServiceProvider::class,
        Domain\Roles\Providers\RolesServiceProvider::class,
        Domain\AccessControl\Providers\AccessControlServiceProvider::class,
        Domain\Extensions\Providers\ExtensionsServiceProvider::class,
        Domain\Extensions\Providers\ExtensionsSdkServiceProvider::class,
        // Позиция значима: маршрутизация обязана отработать boot() РАНЬШЕ
        // AdminServiceProvider, иначе catch-all админки затенит маршруты ядра.
        Domain\Routing\RoutingServiceProvider::class,
        Admin\Providers\AdminServiceProvider::class,
        Domain\SchemaModel\Providers\SchemaServiceProvider::class,
        Domain\SchemaModelValidation\Providers\SchemaModelValidationServiceProvider::class,
        Domain\RecordDefinitions\Providers\RecordDefinitionServiceProvider::class,
        Domain\Records\Providers\RecordsServiceProvider::class,
        Domain\Media\Providers\MediaServiceProvider::class,
        Domain\TableConfig\Providers\TableConfigServiceProvider::class,
        Domain\Menu\Providers\MenuServiceProvider::class,
        Domain\EntryView\Providers\EntryViewServiceProvider::class,
        TemplateEngine\Providers\TemplateEngineServiceProvider::class,
        Domain\DisplayViews\Providers\DisplayViewsServiceProvider::class,
        Domain\RecordIndexes\Providers\RecordIndexesServiceProvider::class,
    ];

    /** @var list<string> */
    private const CONFIG_KEYS = [
        'admin',
        'authentication',
        'errors',
        'media',
        'plugins',
        'records',
        'routing',
        'secret_redaction',
        'security',
        'validation_constraints',
    ];

    /** @var list<class-string> */
    private const COMMANDS = [
        PreflightCommand::class,
        GenerateFeCapabilityCatalogCommand::class,
        PruneAuthSessionsCommand::class,
        PruneOAuthCredentialsCommand::class,
        PluginsListCommand::class,
        PluginsInstallCommand::class,
        PluginsBuildCommand::class,
        LintRoutesCommand::class,
        RebuildRecordDefinitionDisplayViewsCommand::class,
        RecordIndexesDoctorCommand::class,
    ];

    public function register(): void
    {
        $this->mergePlatformConfigs();
        $this->app['config']->set('view.paths', [self::VIEW_PATH]);
        $this->registerFrameworkIntegration();
        $this->commands(self::COMMANDS);

        foreach (self::PROVIDERS as $provider) {
            $this->app->register($provider);
        }
    }

    private function mergePlatformConfigs(): void
    {
        foreach (self::CONFIG_KEYS as $key) {
            $this->mergeConfigFrom(__DIR__."/../config/{$key}.php", $key);
        }
    }

    private function registerFrameworkIntegration(): void
    {
        $noRedirect = static fn (Request $request): ?string => null;
        EncryptCookies::except(SessionCookie::NAME);

        $configureKernel = static function (HttpKernelContract $kernel) use ($noRedirect): void {
            if ($kernel instanceof HttpKernel) {
                // ApplicationBuilder installs a web-login fallback while resolving the kernel;
                // the API-only product has no login page, so the composition root owns
                // the final redirect rule after that framework callback has run.
                Authenticate::redirectUsing($noRedirect);
                AuthenticateSession::redirectUsing($noRedirect);
                AuthenticationException::redirectUsing($noRedirect);
                $kernel->prependMiddleware(CanonicalUrl::class);
                $kernel->appendMiddlewareToGroup('api', VerifyApiCsrf::class);
                $kernel->appendMiddlewareToGroup('api', AddCacheVary::class);
                $kernel->setMiddlewareAliases(array_merge($kernel->getMiddlewareAliases(), [
                    ResolveSessionCredential::ALIAS => ResolveSessionCredential::class,
                    AuthenticateOAuthResource::ALIAS => AuthenticateOAuthResource::class,
                    'no-cache-auth' => NoCacheAuth::class,
                    RequireCapability::ALIAS => RequireCapability::class,
                ]));
            }
        };
        $this->app->afterResolving(HttpKernelContract::class, $configureKernel);
        if ($this->app->resolved(HttpKernelContract::class)) {
            $configureKernel($this->app->make(HttpKernelContract::class));
        }

        $this->app->afterResolving(Handler::class, static function (Handler $handler): void {
            // ErrorKernel is the sole reporter for requests rendered as Problem JSON.
            // Laravel's default reporter must not write the same 4xx/5xx a second time.
            $handler->dontReportWhen(
                static fn (Throwable $exception): bool => ! app()->runningInConsole()
                    && ApiErrorHandler::handles($exception, request()),
            );
            $handler->renderable(
                static fn (Throwable $exception, Request $request) => app(ApiErrorHandler::class)
                    ->handle($exception, $request),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'polymorph');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config' => config_path(),
            ], 'polymorph-config');
        }
    }
}
