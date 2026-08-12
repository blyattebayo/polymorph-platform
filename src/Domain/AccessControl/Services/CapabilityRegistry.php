<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use InvalidArgumentException;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;

final class CapabilityRegistry
{
    /**
     * @var list<CapabilityDefinition>|null
     */
    private ?array $memoizedDefinitions = null;

    /**
     * @param  iterable<CapabilityDefinitionProvider>  $providers
     */
    public function __construct(private readonly iterable $providers) {}

    /**
     * The installed extension set cannot change in a running process. Installation
     * requires restart, so the catalog is collected once per process.
     *
     * @return list<CapabilityDefinition>
     */
    public function capabilityDefinitions(): array
    {
        return $this->memoizedDefinitions ??= $this->collectDefinitions();
    }

    /**
     * @return list<CapabilityDefinition>
     */
    private function collectDefinitions(): array
    {
        $definitions = [];
        $seen = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->capabilities() as $definition) {
                $key = $definition->key();

                if (isset($seen[$key])) {
                    throw new InvalidArgumentException(sprintf(
                        'Capability "%s" is declared more than once (provider %s).',
                        $key,
                        $provider::class,
                    ));
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

        $known = [];
        foreach ($this->capabilityDefinitions() as $definition) {
            $known[$definition->key()] = true;
        }

        foreach ($merged as $roleCode => $capabilityKeys) {
            $unique = array_values(array_unique($capabilityKeys));

            // Состав роли задаётся строковыми ключами, а набор capability —
            // типизированным DSL, и синхронность двух списков ничем не держится.
            // Раньше разошедшийся ключ (опечатка в действии, забытый tag()
            // провайдера) сидер молча пропускал, и роль оставалась пустой: ни
            // исключения, ни лога, а на проде «пользователю ничего не видно».
            // Метод зовут только сидеры и консоль — падать здесь безопасно.
            foreach ($unique as $capabilityKey) {
                if (! isset($known[$capabilityKey])) {
                    throw new InvalidArgumentException(sprintf(
                        'Role "%s" is assigned an unknown capability "%s".',
                        $roleCode,
                        $capabilityKey,
                    ));
                }
            }

            $merged[$roleCode] = $unique;
        }

        return $merged;
    }
}
