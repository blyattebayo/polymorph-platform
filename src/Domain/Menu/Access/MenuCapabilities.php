<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Access;

use Polymorph\Platform\Domain\AccessControl\Access\CapabilitySet;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Access-манифест домена: ресурс, определения capability, дефолтные роли и
 * route-требования — в одном файле.
 */
final class MenuCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'menu';

    public function capabilities(): array
    {
        return CapabilitySet::for(self::RESOURCE)
            ->manage('Manage navigation menu')
            ->all();
    }

    public function defaultRoleAssignments(): array
    {
        // system.admin намеренно не перечислен: сидер пропускает его (wildcard
        // покрывает всё), а мёртвое назначение только вводило в заблуждение.
        return [];
    }

    public static function requireManage(): string
    {
        return CapabilityCatalog::requirement(self::RESOURCE, CapabilityCatalog::ACTION_MANAGE);
    }
}
