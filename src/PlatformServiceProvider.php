<?php

declare(strict_types=1);

namespace Polymorph\Platform;

use Illuminate\Support\ServiceProvider;

/**
 * The heart of the blyattebayo/polymorph package: registers the platform's domain service
 * providers (in dependency order), merges the 12 platform config files, and loads the
 * package's migrations + translations. Auto-discovered via composer extra.laravel.providers,
 * so the thin host's bootstrap/providers.php is empty (ADR 0006 §4.2).
 */
final class PlatformServiceProvider extends ServiceProvider
{
    /**
     * Domain providers in dependency order (mirrors the former bootstrap/providers.php).
     * Order is load-bearing: PipelineCore early; Schema -> RecordDefinitions -> Records ->
     * Materialization.
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
        Domain\Materialization\Providers\MaterializationServiceProvider::class,
    ];

    public function register(): void
    {
        $this->mergePlatformConfigs();

        foreach (self::PROVIDERS as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Merge every config file shipped in the package (config key = file basename).
     * The 11 host-owned configs (app, auth, cache, cors, database, filesystems,
     * logging, mail, queue, services, session) live in the host and are not here.
     *
     * There used to be an exception list: config/errors.php held exception-builder
     * Closures, which are not var_export-serializable, so merging it broke
     * `config:cache`. Those Closures became code (FrameworkErrorResolver,
     * ErrorReportPolicy) and the file is plain data again — no exception needed.
     */
    private function mergePlatformConfigs(): void
    {
        foreach (glob(__DIR__.'/../config/*.php') ?: [] as $file) {
            $this->mergeConfigFrom($file, basename($file, '.php'));
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'polymorph');
        $this->registerViews();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config' => config_path(),
            ], 'polymorph-config');
        }
    }

    /**
     * Register the platform's Blade views (errors, public site, layouts, page
     * templates) so a thin host renders web routes out of the box.
     *
     * They are added to the DEFAULT namespace's search path — not a `polymorph::`
     * hint — so existing bare view names (view('home.default'), view('errors.404'),
     * page templates) resolve with zero
     * controller changes. The package path is appended AFTER config('view.paths'),
     * so a host that publishes its own views (resource_path('views'), searched
     * first) transparently overrides the package.
     */
    private function registerViews(): void
    {
        $viewsPath = __DIR__.'/../resources/views';

        $this->callAfterResolving('view.finder', static function ($finder) use ($viewsPath): void {
            $finder->addLocation($viewsPath);
        });

        $this->publishes([
            $viewsPath => resource_path('views'),
        ], 'polymorph-views');
    }
}
