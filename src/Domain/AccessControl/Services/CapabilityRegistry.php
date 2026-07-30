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
    public function __construct(
        private readonly iterable $providers,
    ) {}

    /**
     * Мемоизация на время жизни инстанса (биндинг — scoped, т.е. один запрос):
     * без неё каждый вызов заново обходил провайдеров, а ExtensionsCapabilityProvider
     * на каждом проходе делал SQL-запрос + обход ФС (аудит, 6.6). Scoped, а не
     * singleton: включение плагина в живом процессе должно попадать в каталог
     * следующего запроса.
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
