<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Providers;

use Composer\Autoload\ClassLoader;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Extensions\Access\ExtensionsCapabilityProvider;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;
use Polymorph\Platform\Domain\Extensions\Events\EloquentRecordDefinitionSchemaCode;
use Polymorph\Platform\Domain\Extensions\Events\RecordDefinitionSchemaCode;
use Polymorph\Platform\Domain\Extensions\Events\RecordLifecycleSdkBridge;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionAclManifestParser;
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
        $this->app->singleton(SecretRedactor::class, PayloadRedactor::class);
        $this->app->singleton(RecordDefinitionSchemaCode::class, EloquentRecordDefinitionSchemaCode::class);

        // One immutable installed set is validated before any extension code loads.
        $extensions = $this->app->make(ExtensionDiscoveryService::class)->discoverAll();
        $this->registerExtensionAutoload($extensions);
        $this->registerExtensionProviders($extensions);
    }

    public function boot(): void
    {
        $this->app->tag([ExtensionsCapabilityProvider::class], 'access.capability_providers');
        Event::listen(RecordDeleted::class, RecordLifecycleSdkBridge::class);
        Event::listen(MigrationsEnded::class, fn (): mixed => $this->provisionAccessControl());
    }

    /** @param list<DiscoveredExtension> $extensions */
    private function registerExtensionAutoload(array $extensions): void
    {
        foreach ($extensions as $extension) {
            $autoloadPath = dirname($extension->manifestPath).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
            if (! is_file($autoloadPath)) {
                throw new ExtensionException("Plugin '{$extension->id}' artifact has no vendor/autoload.php.");
            }

            /** @var mixed $loader */
            $loader = require $autoloadPath;
            if (! $loader instanceof ClassLoader) {
                throw new ExtensionException(
                    "Plugin '{$extension->id}' vendor/autoload.php did not return a Composer ClassLoader.",
                );
            }

            // Plugin dependencies must never shadow host contracts.
            $loader->unregister();
            $loader->register(false);
        }
    }

    /** @param list<DiscoveredExtension> $extensions */
    private function registerExtensionProviders(array $extensions): void
    {
        foreach ($extensions as $extension) {
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
        $service = $this->app->make(ExtensionCapabilityService::class);
        foreach ($this->app->make(ExtensionDiscoveryService::class)->discoverAll() as $extension) {
            $service->provision($extension);
        }
    }
}
