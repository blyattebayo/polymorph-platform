<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Access;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Access-манифест домена: ресурс, определения capability и дефолтные роли —
 * в одном файле (раньше пара Capabilities + CapabilityProvider).
 */
final class MenuCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'menu';

    public function capabilities(): array
    {
        return [
            new CapabilityDefinition(self::RESOURCE, CapabilityCatalog::ACTION_MANAGE, 'Manage navigation menu'),
        ];
    }

    public function defaultRoleAssignments(): array
    {
        // system.admin намеренно не перечислен: сидер пропускает его (wildcard
        // покрывает всё), а мёртвое назначение только вводило в заблуждение.
        return [];
    }
}
