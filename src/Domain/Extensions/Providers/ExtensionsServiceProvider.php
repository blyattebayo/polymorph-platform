<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Extensions\Access\ExtensionsCapabilityProvider;
use Polymorph\Platform\Domain\Extensions\Events\EloquentRecordDefinitionSchemaCode;
use Polymorph\Platform\Domain\Extensions\Events\RecordDefinitionSchemaCode;
use Polymorph\Platform\Domain\Extensions\Events\RecordLifecycleSdkBridge;
use Polymorph\Platform\Domain\Extensions\Routing\ExtensionRouteCatalogAdapter;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionAclManifestParser;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionAutoloadService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionCapabilityService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionCompatibilityService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionDiscoveryService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionFrontendManifestService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionManager;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionManifestValidator;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionMigrationService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionRegistryService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionRouteConstraints;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionRouteService;
use Polymorph\Platform\Domain\Extensions\Trusted\TrustedExtensionSource;
use Polymorph\Platform\Domain\Records\Events\RecordDeleted;
use Polymorph\Platform\Domain\Routing\Core\Contracts\PluginRouteCatalog;
use Polymorph\Platform\Support\Logging\Contracts\SecretRedactor;
use Polymorph\Platform\Support\Logging\PayloadRedactor;

final class ExtensionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TrustedExtensionSource::class);
        $this->app->singleton(ExtensionAclManifestParser::class);
        $this->app->singleton(ExtensionManifestValidator::class);
        $this->app->singleton(ExtensionDiscoveryService::class);
        $this->app->singleton(ExtensionRegistryService::class);
        $this->app->singleton(ExtensionRouteConstraints::class);
        $this->app->singleton(ExtensionRouteService::class);
        $this->app->singleton(ExtensionCapabilityService::class);
        $this->app->singleton(ExtensionCompatibilityService::class);
        $this->app->singleton(ExtensionMigrationService::class);
        $this->app->singleton(ExtensionFrontendManifestService::class);
        $this->app->singleton(ExtensionManager::class);
        $this->app->singleton(ExtensionAutoloadService::class);

        $this->app->singleton(SecretRedactor::class, PayloadRedactor::class);

        // SDK event bridge: platform-internal record events -> declared Polymorph\Sdk\Events\*
        // contract that extensions subscribe to (ADR 0005 Фаза 4).
        $this->app->singleton(RecordDefinitionSchemaCode::class, EloquentRecordDefinitionSchemaCode::class);

        $this->app->singleton(
            PluginRouteCatalog::class,
            ExtensionRouteCatalogAdapter::class,
        );

        $this->app->make(ExtensionAutoloadService::class)->registerAutoload();
        $this->registerExtensionProviders();
    }

    public function boot(): void
    {
        foreach ($this->app->make(ExtensionMigrationService::class)->discoverMigrationPaths() as $migrationPath) {
            $this->loadMigrationsFrom($migrationPath);
        }

        $this->app->tag([ExtensionsCapabilityProvider::class], 'access.capability_providers');

        // Re-emit internal record-lifecycle events as the SDK contract for extension listeners.
        Event::listen(RecordDeleted::class, RecordLifecycleSdkBridge::class);
    }

    private function registerExtensionProviders(): void
    {
        foreach ($this->app->make(ExtensionDiscoveryService::class)->discoverAll() as $extension) {
            $providerClass = $extension->providerClass;
            if (! is_string($providerClass) || $providerClass === '') {
                continue;
            }

            if (! class_exists($providerClass) || ! is_subclass_of($providerClass, ServiceProvider::class)) {
                continue;
            }

            $this->app->register($providerClass);
        }
    }
}
