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
     * @var array<int, class-string>
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
        Domain\Routing\Providers\RoutingServiceProvider::class,
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

    /**
     * Config files that live in the package's config/ directory but are NOT merged
     * into the config repository.
     *
     * 'errors' stores exception-builder Closures as values, which are not
     * var_export-serializable — merging it would make `php artisan config:cache` /
     * `optimize` throw Closure::__set_state(). AppServiceProvider loads that file
     * directly instead, so the app stays fully config-cacheable.
     *
     * @var array<int, string>
     */
    private const UNMERGED_CONFIGS = ['errors'];

    public function register(): void
    {
        $this->mergePlatformConfigs();

        foreach (self::PROVIDERS as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Merge every config file shipped in the package (config key = file basename),
     * except the UNMERGED_CONFIGS exceptions. The 11 host-owned configs (app, auth,
     * cache, cors, database, filesystems, logging, mail, queue, services, session)
     * live in the host and are not present here.
     */
    private function mergePlatformConfigs(): void
    {
        foreach (glob(__DIR__.'/../config/*.php') ?: [] as $file) {
            $key = basename($file, '.php');

            if (! in_array($key, self::UNMERGED_CONFIGS, true)) {
                $this->mergeConfigFrom($file, $key);
            }
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
     * content route_nodes with a VIEW action, page templates) resolve with zero
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
