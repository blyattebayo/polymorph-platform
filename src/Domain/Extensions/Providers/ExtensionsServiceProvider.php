<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Providers;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Extensions\Access\ExtensionsCapabilityProvider;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException;
use Polymorph\Platform\Domain\Extensions\Events\EloquentRecordDefinitionSchemaCode;
use Polymorph\Platform\Domain\Extensions\Events\RecordDefinitionSchemaCode;
use Polymorph\Platform\Domain\Extensions\Events\RecordLifecycleSdkBridge;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionAclManifestParser;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionAutoloadService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionCapabilityService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionDiscoveryService;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionFrontendManifestService;
use Polymorph\Platform\Domain\Records\Events\RecordDeleted;
use Polymorph\Platform\Support\Logging\Contracts\SecretRedactor;
use Polymorph\Platform\Support\Logging\PayloadRedactor;
use Polymorph\Sdk\Extension\ExtensionProvider;

final class ExtensionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExtensionAclManifestParser::class);
        $this->app->singleton(ExtensionDiscoveryService::class);
        $this->app->singleton(ExtensionCapabilityService::class);
        $this->app->singleton(ExtensionFrontendManifestService::class);
        $this->app->singleton(ExtensionAutoloadService::class);
        $this->app->singleton(SecretRedactor::class, PayloadRedactor::class);
        $this->app->singleton(RecordDefinitionSchemaCode::class, EloquentRecordDefinitionSchemaCode::class);

        // Validate the complete installed set before loading any extension code.
        $this->app->make(ExtensionDiscoveryService::class)->discoverAll();
        $this->app->make(ExtensionAutoloadService::class)->registerAutoload();
        $this->registerExtensionProviders();
    }

    public function boot(): void
    {
        $this->app->tag([ExtensionsCapabilityProvider::class], 'access.capability_providers');
        Event::listen(RecordDeleted::class, RecordLifecycleSdkBridge::class);
        Event::listen(MigrationsEnded::class, fn (): mixed => $this->provisionAccessControl());
        $this->provisionAccessControl();
    }

    private function registerExtensionProviders(): void
    {
        foreach ($this->app->make(ExtensionDiscoveryService::class)->discoverAll() as $extension) {
            $providerClass = $extension->providerClass;
            if (! class_exists($providerClass) || ! is_subclass_of($providerClass, ExtensionProvider::class)) {
                throw new ExtensionException(
                    "Plugin '{$extension->id}' provider '{$providerClass}' must extend ".ExtensionProvider::class.'.',
                );
            }

            /** @var ExtensionProvider $provider */
            $provider = new $providerClass($this->app);
            if ($provider->declaredExtensionId() !== $extension->id) {
                throw new ExtensionException(
                    "Plugin '{$extension->id}' provider declares id '{$provider->declaredExtensionId()}'.",
                );
            }

            $this->app->register($provider);
        }
    }

    private function provisionAccessControl(): void
    {
        if (! Schema::hasTable('roles')
            || ! Schema::hasTable('ac_policies')
            || ! Schema::hasTable('ac_assignments')) {
            return;
        }

        $service = $this->app->make(ExtensionCapabilityService::class);
        foreach ($this->app->make(ExtensionDiscoveryService::class)->discoverAll() as $extension) {
            $service->ensurePluginRoles($extension);
            $service->assignDefaultPluginAdminPolicy($extension);
        }
    }
}
