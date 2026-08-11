<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Roles\Access\RolesCapabilities;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleAssignmentRepository;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleRepository;
use Polymorph\Platform\Domain\Roles\Core\Contracts\UserRoleReadModel;
use Polymorph\Platform\Domain\Roles\Infrastructure\Repositories\EloquentRoleAssignmentRepository;
use Polymorph\Platform\Domain\Roles\Infrastructure\Repositories\EloquentRoleRepository;
use Polymorph\Platform\Domain\Roles\Infrastructure\Repositories\EloquentUserRoleReadModel;
use Polymorph\Platform\Domain\Roles\Listeners\SyncUserAccessOnCreated;
use Polymorph\Platform\Domain\Roles\Services\AccessProvisioner;
use Polymorph\Platform\Domain\Users\Events\UserCreated;

final class RolesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RoleRepository::class, EloquentRoleRepository::class);
        $this->app->singleton(RoleAssignmentRepository::class, EloquentRoleAssignmentRepository::class);
        $this->app->singleton(UserRoleReadModel::class, EloquentUserRoleReadModel::class);
        $this->app->singleton(AccessProvisioner::class, AccessProvisioner::class);
    }

    public function boot(): void
    {
        Event::listen(UserCreated::class, SyncUserAccessOnCreated::class);

        $this->app->tag([RolesCapabilities::class], 'access.capability_providers');
    }
}
