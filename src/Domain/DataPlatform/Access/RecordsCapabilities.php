<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Access;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\AccessControl\Access\CapabilitySet;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class RecordsCapabilities implements CapabilityDefinitionProvider
{
    public const RESOURCE = 'records';

    public function capabilities(): array
    {
        return CapabilitySet::for(self::RESOURCE)
            ->read('Read records')
            ->write('Write records')
            ->delete('Delete records')
            ->all();
    }

    public function defaultRoleAssignments(): array
    {
        return [
            BuiltInRoleCatalog::ROLE_RECORDS_EDITOR => CapabilityCatalog::keys(self::RESOURCE, 'read', 'write'),
            BuiltInRoleCatalog::ROLE_RECORDS_ADMIN => CapabilityCatalog::keys(self::RESOURCE, 'read', 'write', 'delete'),
        ];
    }
}
