<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;

interface CapabilityDefinitionProvider
{
    /**
     * @return list<CapabilityDefinition>
     */
    public function capabilities(): array;

    /**
     * Default capability keys assigned to built-in roles on seed.
     *
     * @return array<string, list<string>> roleCode => capabilityKeys
     */
    public function defaultRoleAssignments(): array;
}
