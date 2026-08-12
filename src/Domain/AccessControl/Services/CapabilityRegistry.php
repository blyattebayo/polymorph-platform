<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use InvalidArgumentException;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CapabilityDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final class CapabilityRegistry
{
    /**
     * @var list<CapabilityDefinition>|null
     */
    private ?array $memoizedDefinitions = null;

    /**
     * @var array<string, true>
     */
    private array $duplicateKeys = [];

    /**
     * @param  iterable<CapabilityDefinitionProvider>  $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly AppLogger $logger,
    ) {}

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

                // Дубль НЕ роняет каталог: он собирается на горячем пути
                // (/auth/current и логин), а один из его источников —
                // манифесты включённых расширений. Раньше плагин с задвоенной
                // парой resource+action проходил установку и закрывал вход в
                // админку всем пользователям. Побеждает первое определение,
                // повтор уходит в лог; отвергать дубли — дело установки
                // расширения и генератора FE-каталога, где падать полезно.
                if (isset($seen[$key])) {
                    $this->duplicateKeys[$key] = true;
                    $this->logger->warning('access.duplicate_capability_definition', [
                        'capability' => $key,
                        'provider' => $provider::class,
                    ]);

                    continue;
                }

                $seen[$key] = true;
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * Ключи, объявленные более одного раза при последней сборке каталога.
     *
     * @return list<string>
     */
    public function duplicateCapabilityKeys(): array
    {
        $this->capabilityDefinitions();

        return array_keys($this->duplicateKeys);
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
