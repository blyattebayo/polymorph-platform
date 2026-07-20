<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessSubjectProvider;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRuntime;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final class EffectiveCapabilityResolver
{
    public function __construct(
        private readonly PolicyRuntime $runtime,
        private readonly AccessSubjectProvider $subjectProvider,
        private readonly CapabilityRegistry $capabilityRegistry,
    ) {
    }

    /**
     * @return list<string>
     */
    public function for(UserIdentity $user): array
    {
        $subjects = $this->subjectProvider->for($user);
        $checks = [];
        $keys = [];

        foreach ($this->capabilityRegistry->capabilityDefinitionsAsArrays() as $definition) {
            $resource = trim((string) ($definition['resource'] ?? ''));
            $action = trim((string) ($definition['action'] ?? ''));
            if ($resource === '' || $action === '') {
                continue;
            }

            $checks[] = ['resource' => $resource, 'action' => $action];
            $keys[] = CapabilityCatalog::capabilityKey($resource, $action);
        }

        if ($checks === []) {
            return [];
        }

        $decisions = $this->runtime->batchEvaluate($subjects, $checks);
        $allowed = [];

        foreach ($decisions as $index => $decision) {
            if ($decision->allowed()) {
                $allowed[] = $keys[$index];
            }
        }

        sort($allowed, SORT_STRING);

        return array_values(array_unique($allowed));
    }
}
