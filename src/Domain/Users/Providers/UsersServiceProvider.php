<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\Users\Access\UsersCapabilityProvider;
use Polymorph\Platform\Domain\Users\Core\Contracts\SystemAdministratorGuard;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Events\PasswordChanged;
use Polymorph\Platform\Domain\Users\Events\UserCreated;
use Polymorph\Platform\Domain\Users\Events\UserUpdated;
use Polymorph\Platform\Domain\Users\Infrastructure\Repositories\EloquentUserRepository;
use Polymorph\Platform\Domain\Users\Listeners\LogUserLifecycleEvent;
use Polymorph\Platform\Domain\Users\Listeners\NotifyPasswordChanged;
use Polymorph\Platform\Domain\Users\Listeners\RevokeSessionsAfterPasswordChanged;
use Polymorph\Platform\Domain\Users\Listeners\RevokeSessionsAfterUserStatusChanged;
use Polymorph\Platform\Domain\Users\Observers\UserObserver;
use Polymorph\Platform\Domain\Users\Services\RoleBasedSystemAdministratorGuard;

final class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserRepository::class, EloquentUserRepository::class);
        $this->app->singleton(SystemAdministratorGuard::class, RoleBasedSystemAdministratorGuard::class);
    }

    public function boot(): void
    {
        User::observe(UserObserver::class);

        Event::listen(UserCreated::class, [LogUserLifecycleEvent::class, 'handleUserCreated']);
        Event::listen(UserUpdated::class, [LogUserLifecycleEvent::class, 'handleUserUpdated']);
        Event::listen(UserUpdated::class, RevokeSessionsAfterUserStatusChanged::class);
        Event::listen(PasswordChanged::class, [LogUserLifecycleEvent::class, 'handlePasswordChanged']);
        Event::listen(PasswordChanged::class, RevokeSessionsAfterPasswordChanged::class);
        Event::listen(PasswordChanged::class, NotifyPasswordChanged::class);

        $this->app->tag([UsersCapabilityProvider::class], 'access.capability_providers');
    }
}
