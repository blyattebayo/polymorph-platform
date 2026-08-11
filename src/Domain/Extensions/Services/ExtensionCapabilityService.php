<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;
use Polymorph\Platform\Domain\Roles\Core\Models\Role;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class ExtensionCapabilityService
{
    public function __construct(
        private readonly AccessControlAdministration $adminService,
    ) {}

    public function assignDefaultPluginAdminPolicy(DiscoveredExtension $plugin): void
    {
        // Дефолтная админ-капабилити зоны ADMIN_API: ей защищаются маршруты,
        // за которые манифест не объявил requires: (см. PluginRouteMounter).
        // Создаётся независимо от наличия capabilityDefinitions — админ-зона
        // без манифестных капабилити всё равно должна быть закрыта.
        $adminZonePolicy = $this->adminService->ensurePolicy([
            'resource_pattern' => "ext.{$plugin->id}.admin",
            'action' => CapabilityCatalog::ACTION_ACCESS,
            'effect' => CapabilityCatalog::EFFECT_ALLOW,
            'priority' => 100,
            'is_active' => true,
            'metadata' => [
                'source' => 'plugin-admin-zone',
                'plugin_id' => $plugin->id,
            ],
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
                'effect' => CapabilityCatalog::EFFECT_ALLOW,
                'priority' => 100,
                'is_active' => true,
                'metadata' => [
                    'source' => 'plugin-default-admin-capability',
                    'plugin_id' => $plugin->id,
                ],
            ]);

            foreach ($subjects as $subject) {
                $this->adminService->assign((int) $capabilityPolicy->id, $subject);
            }
        }
    }

    public function ensurePluginRoles(DiscoveredExtension $plugin): void
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
                    'effect' => CapabilityCatalog::EFFECT_ALLOW,
                    'priority' => 100,
                    'is_active' => true,
                    'metadata' => [
                        'source' => 'plugin-role',
                        'plugin_id' => $plugin->id,
                        'role' => $role->code,
                    ],
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
