<?php

declare(strict_types=1);

namespace Polymorph\Platform;

use Illuminate\Support\ServiceProvider;

/**
 * The heart of the polymorph/platform package: registers the platform's domain service
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
     * Platform config files (config key = file basename). The 11 host-owned configs
     * (app, auth, cache, cors, database, filesystems, logging, mail, queue, services,
     * session) stay in the host and are NOT merged here.
     *
     * @var array<int, string>
     */
    private const CONFIG_KEYS = [
        'dynamic-routes',
        // NB: 'errors' is intentionally NOT merged into config(). config/errors.php stores
        // exception-builder Closures as values, which are not var_export-serializable — merging
        // them into the config repository would make `php artisan config:cache` / `optimize` throw
        // Closure::__set_state(). AppServiceProvider loads that file directly instead, so the app
        // stays fully config-cacheable. See AppServiceProvider::register().
        'jwt',
        'materialization',
        'media',
        'pat',
        'plugins',
        'records',
        'routing',
        'secret_redaction',
        'security',
        'validation_constraints',
    ];

    public function register(): void
    {
        foreach (self::CONFIG_KEYS as $key) {
            $this->mergeConfigFrom(__DIR__ . "/../config/{$key}.php", $key);
        }

        foreach (self::PROVIDERS as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'polymorph');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config' => config_path(),
            ], 'polymorph-config');
        }
    }
}
