<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Providers;

use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\AccessControl\Access\AccessControlCapabilities;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Services\AccessControlAdminService;
use Polymorph\Platform\Domain\AccessControl\Services\CapabilityRegistry;
use Polymorph\Platform\Domain\AccessControl\Services\PolicyAccessGate;
use Polymorph\Platform\SharedKernel\Access\AccessGate;

final class AccessControlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AccessControlAdministration::class, AccessControlAdminService::class);
        $this->app->scoped(AccessGate::class, PolicyAccessGate::class);

        // Installed extensions are immutable for the lifetime of a process. Installation
        // requires a restart, so one catalog per process is the only supported state.
        $this->app->singleton(CapabilityRegistry::class, function ($app): CapabilityRegistry {
            /** @var iterable<CapabilityDefinitionProvider> $providers */
            $providers = $app->tagged('access.capability_providers');

            return new CapabilityRegistry($providers);
        });
    }

    public function boot(): void
    {
        $this->app->tag([
            AccessControlCapabilities::class,
        ], 'access.capability_providers');
    }
}
