<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Providers;

use Illuminate\Support\ServiceProvider;
use Polymorph\Platform\Domain\AccessControl\Access\AccessControlCapabilityProvider;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessSubjectProvider;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\ActionDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\ActionRegistry;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AssignmentRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AuditActorResolver;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AuditEventRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CompiledPolicyRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyCompilationService;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRuntime;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\ResourceMatcher;
use Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories\EloquentAssignmentRepository;
use Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories\EloquentAuditEventRepository;
use Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories\EloquentCompiledPolicyRepository;
use Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories\EloquentPolicyRepository;
use Polymorph\Platform\Domain\AccessControl\Services\AccessControlAdminService;
use Polymorph\Platform\Domain\AccessControl\Services\CapabilityRegistry;
use Polymorph\Platform\Domain\AccessControl\Services\ConfigurableActionRegistry;
use Polymorph\Platform\Domain\AccessControl\Services\CurrentAuditActorResolver;
use Polymorph\Platform\Domain\AccessControl\Services\DefaultPolicyRuntime;
use Polymorph\Platform\Domain\AccessControl\Services\DotPrefixResourceMatcher;
use Polymorph\Platform\Domain\AccessControl\Services\PolicyCompiler;
use Polymorph\Platform\Domain\AccessControl\Services\RoleAwareAccessSubjectProvider;

final class AccessControlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PolicyRepository::class, EloquentPolicyRepository::class);
        $this->app->singleton(AssignmentRepository::class, EloquentAssignmentRepository::class);
        $this->app->singleton(CompiledPolicyRepository::class, EloquentCompiledPolicyRepository::class);
        $this->app->singleton(AuditEventRepository::class, EloquentAuditEventRepository::class);
        $this->app->singleton(ActionRegistry::class, function ($app): ActionRegistry {
            /** @var iterable<ActionDefinitionProvider> $providers */
            $providers = $app->tagged('access.action_providers');

            return new ConfigurableActionRegistry($providers);
        });
        $this->app->bind(ResourceMatcher::class, DotPrefixResourceMatcher::class);
        // scoped, не singleton: in-memory кэш субъектов в RoleAwareAccessSubjectProvider
        // должен жить в пределах одного запроса. Под Octane/queue singleton протухал бы
        // между запросами — пользователь сохранял бы доступ снятой роли (см. B2).
        $this->app->scoped(AccessSubjectProvider::class, RoleAwareAccessSubjectProvider::class);
        $this->app->bind(AuditActorResolver::class, CurrentAuditActorResolver::class);
        $this->app->bind(PolicyCompilationService::class, PolicyCompiler::class);
        $this->app->bind(AccessControlAdministration::class, AccessControlAdminService::class);
        $this->app->bind(PolicyRuntime::class, DefaultPolicyRuntime::class);

        $this->app->singleton(CapabilityRegistry::class, function ($app): CapabilityRegistry {
            /** @var iterable<CapabilityDefinitionProvider> $providers */
            $providers = $app->tagged('access.capability_providers');

            return new CapabilityRegistry($providers);
        });
    }

    public function boot(): void
    {
        $this->app->tag([
            AccessControlCapabilityProvider::class,
        ], 'access.capability_providers');
    }
}
