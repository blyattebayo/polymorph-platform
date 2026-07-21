<?php

declare(strict_types=1);

namespace Polymorph\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Domain\AccessControl\Services\CapabilityRegistry;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

class BuiltInPoliciesSeeder extends Seeder
{
    /**
     * @return array<int, array{resource: string, action: string, effect: string}>
     */
    private static function policies(): array
    {
        return array_map(
            static fn (array $definition): array => [
                $definition['resource'],
                $definition['action'],
                CapabilityCatalog::EFFECT_ALLOW,
            ],
            app(CapabilityRegistry::class)->capabilityDefinitionsAsArrays(),
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private static function roleCapabilityMap(): array
    {
        return app(CapabilityRegistry::class)->defaultRoleAssignments();
    }

    public function run(): void
    {
        $this->call(BuiltInRolesSeeder::class);

        /** @var AccessControlAdministration $policyAdmin */
        $policyAdmin = app(AccessControlAdministration::class);

        $policyIdsByKey = [];

        foreach (self::policies() as [$resource, $action, $effect]) {
            $created = $policyAdmin->ensurePolicy([
                'resource_pattern' => $resource,
                'action' => $action,
                'effect' => $effect,
                'priority' => 100,
                'is_active' => true,
            ]);

            $policyIdsByKey[CapabilityCatalog::capabilityKey($resource, $action)] = (int) $created->id;
        }

        $wildcardPolicy = $policyAdmin->ensurePolicy([
            'resource_pattern' => '*',
            'action' => CapabilityCatalog::ACTION_WILDCARD,
            'effect' => CapabilityCatalog::EFFECT_ALLOW,
            'priority' => 100,
            'is_active' => true,
        ]);

        $policyAdmin->setSubjectPolicies(Subject::role(BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN), [(int) $wildcardPolicy->id]);

        foreach (self::roleCapabilityMap() as $roleCode => $capabilityKeys) {
            if ($roleCode === BuiltInRoleCatalog::ROLE_SYSTEM_ADMIN) {
                continue;
            }

            $subject = Subject::role($roleCode);
            $policyIds = [];

            foreach ($capabilityKeys as $capabilityKey) {
                $policyId = $policyIdsByKey[$capabilityKey] ?? 0;
                if ($policyId <= 0) {
                    continue;
                }

                $policyIds[] = $policyId;
            }

            $policyAdmin->setSubjectPolicies($subject, array_values(array_unique($policyIds)));
        }
    }
}
