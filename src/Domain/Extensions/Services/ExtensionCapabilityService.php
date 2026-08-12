<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Effect;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;
use Polymorph\Platform\Domain\Roles\Core\Models\Role;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class ExtensionCapabilityService
{
    public function __construct(
        private readonly AccessControlAdministration $adminService,
    ) {}

    public function provision(DiscoveredExtension $plugin): void
    {
        DB::transaction(function () use ($plugin): void {
            $this->ensurePluginRoles($plugin);
            $this->assignDefaultPluginAdminPolicy($plugin);
        });
    }

    private function assignDefaultPluginAdminPolicy(DiscoveredExtension $plugin): void
    {
        // Дефолтная админ-капабилити зоны ADMIN_API: ей защищаются маршруты,
        // за которые манифест не объявил requires: (см. PluginRouteMounter).
        // Создаётся независимо от наличия capabilityDefinitions — админ-зона
        // без манифестных капабилити всё равно должна быть закрыта.
        $adminZonePolicy = $this->adminService->ensurePolicy([
            'resource_pattern' => "ext.{$plugin->id}.admin",
            'action' => CapabilityCatalog::ACTION_ACCESS,
            'effect' => Effect::ALLOW->value,
        ]);

        $subjects = $this->defaultAdminSubjects($plugin);
        foreach ($subjects as $subject) {
            $this->adminService->assign((int) $adminZonePolicy->id, $subject);
        }

        if ($plugin->capabilityDefinitions === []) {
            return;
        }
        foreach ($plugin->capabilityDefinitions as $definition) {
            $capabilityPolicy = $this->adminService->ensurePolicy([
                'resource_pattern' => $definition->resource,
                'action' => $definition->action,
                'effect' => Effect::ALLOW->value,
            ]);

            foreach ($subjects as $subject) {
                $this->adminService->assign((int) $capabilityPolicy->id, $subject);
            }
        }
    }

    private function ensurePluginRoles(DiscoveredExtension $plugin): void
    {
        if ($plugin->pluginRoles === []) {
            return;
        }

        foreach ($plugin->pluginRoles as $role) {
            Role::query()->updateOrCreate(
                ['code' => $role->code],
                ['name' => $role->name, 'description' => $role->description],
            );

            $subject = Subject::role($role->code);
            foreach ($role->capabilities as $resource) {
                $policy = $this->adminService->ensurePolicy([
                    'resource_pattern' => $resource,
                    'action' => $this->capabilityAction($plugin, $resource),
                    'effect' => Effect::ALLOW->value,
                ]);

                $this->adminService->assign((int) $policy->id, $subject);
            }
        }
    }

    private function capabilityAction(DiscoveredExtension $plugin, string $resource): string
    {
        foreach ($plugin->capabilityDefinitions as $definition) {
            if ($definition->resource === $resource) {
                return $definition->action;
            }
        }

        return CapabilityCatalog::ACTION_ACCESS;
    }

    /**
     * @return list<Subject>
     */
    private function defaultAdminSubjects(DiscoveredExtension $plugin): array
    {
        return array_values(array_map(
            static fn (string $roleCode): Subject => Subject::role($roleCode),
            $plugin->defaultAdminRoles,
        ));
    }
}
