<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Access\AccessCheck;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;

final class EffectiveCapabilityResolver
{
    public function __construct(
        private readonly AccessGate $gate,
        private readonly CapabilityRegistry $capabilityRegistry,
    ) {}

    /**
     * @return list<string>
     */
    public function for(User $user): array
    {
        $checks = [];
        $keys = [];

        foreach ($this->capabilityRegistry->capabilityDefinitionsAsArrays() as $definition) {
            $resource = $definition['resource'];
            $action = $definition['action'];

            $checks[] = new AccessCheck(ResourceRef::fromString($resource), $action);
            $keys[] = CapabilityCatalog::capabilityKey($resource, $action);
        }

        if ($checks === []) {
            return [];
        }

        $allowed = [];
        foreach ($this->gate->allowsEach($user, $checks) as $index => $isAllowed) {
            if ($isAllowed) {
                $allowed[] = $keys[$index];
            }
        }

        sort($allowed, SORT_STRING);

        return array_values(array_unique($allowed));
    }
}
