<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use InvalidArgumentException;

final class CapabilityRegistry
{
    /**
     * @param iterable<CapabilityDefinitionProvider> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    /**
     * @return list<CapabilityDefinition>
     */
    public function capabilityDefinitions(): array
    {
        $definitions = [];
        $seen = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->capabilities() as $definition) {
                $key = $definition->key();
                if (isset($seen[$key])) {
                    throw new InvalidArgumentException("Duplicate capability definition: {$key}.");
                }

                $seen[$key] = true;
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @return list<array{resource:string,action:string,label:string}>
     */
    public function capabilityDefinitionsAsArrays(): array
    {
        return array_map(
            static fn (CapabilityDefinition $definition): array => $definition->toArray(),
            $this->capabilityDefinitions(),
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public function defaultRoleAssignments(): array
    {
        $merged = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->defaultRoleAssignments() as $roleCode => $capabilityKeys) {
                $merged[$roleCode] = array_merge($merged[$roleCode] ?? [], $capabilityKeys);
            }
        }

        foreach ($merged as $roleCode => $capabilityKeys) {
            $merged[$roleCode] = array_values(array_unique($capabilityKeys));
        }

        return $merged;
    }
}
