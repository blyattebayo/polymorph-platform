<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Providers;

use Polymorph\Platform\Domain\Roles\Access\RolesCapabilityProvider;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleAssignmentRepository;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleRepository;
use Polymorph\Platform\Domain\Roles\Core\Contracts\UserRoleAssignmentGuard;
use Polymorph\Platform\Domain\Roles\Core\Contracts\UserRoleReadModel;
use Polymorph\Platform\Domain\Roles\Infrastructure\Repositories\EloquentRoleAssignmentRepository;
use Polymorph\Platform\Domain\Roles\Infrastructure\Repositories\EloquentRoleRepository;
use Polymorph\Platform\Domain\Roles\Infrastructure\Repositories\EloquentUserRoleReadModel;
use Polymorph\Platform\Domain\Roles\Services\DefaultUserRoleAssignmentGuard;
use Polymorph\Platform\Domain\Roles\Services\RolePrivilegedUserMembership;
use Polymorph\Platform\Domain\Roles\Listeners\SyncUserAccessOnCreated;
use Polymorph\Platform\Domain\Roles\Services\AccessProvisioner;
use Polymorph\Platform\Domain\Roles\Services\RoleAssignmentUserRoleManager;
use Polymorph\Platform\Domain\Users\Core\Contracts\PrivilegedUserMembership;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserRoleManager;
use Polymorph\Platform\Domain\Users\Events\UserCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class RolesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RoleRepository::class, EloquentRoleRepository::class);
        $this->app->singleton(RoleAssignmentRepository::class, EloquentRoleAssignmentRepository::class);
        $this->app->singleton(UserRoleAssignmentGuard::class, DefaultUserRoleAssignmentGuard::class);
        $this->app->singleton(UserRoleReadModel::class, EloquentUserRoleReadModel::class);
        $this->app->singleton(AccessProvisioner::class, AccessProvisioner::class);
        $this->app->singleton(PrivilegedUserMembership::class, RolePrivilegedUserMembership::class);
        $this->app->singleton(UserRoleManager::class, RoleAssignmentUserRoleManager::class);
    }

    public function boot(): void
    {
        Event::listen(UserCreated::class, SyncUserAccessOnCreated::class);

        $this->app->tag([RolesCapabilityProvider::class], 'access.capability_providers');
    }
}