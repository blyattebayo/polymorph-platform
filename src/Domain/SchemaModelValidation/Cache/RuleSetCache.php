<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Cache;

final class RuleSetCache
{
    /**
     * @var array<int, array<string, array<int, string|object>>>
     */
    private array $store = [];

    /**
     * @return array<string, array<int, string|object>>|null
     */
    public function get(int $schemaId): ?array
    {
        if ($schemaId <= 0) {
            return null;
        }

        return $this->store[$schemaId] ?? null;
    }

    /**
     * @param  array<string, array<int, string|object>>  $ruleSet
     */
    public function put(int $schemaId, array $ruleSet): void
    {
        if ($schemaId <= 0) {
            return;
        }

        $this->store[$schemaId] = $ruleSet;
    }

    public function forget(int $schemaId): void
    {
        if ($schemaId <= 0) {
            return;
        }

        unset($this->store[$schemaId]);
    }

    public function flush(): void
    {
        $this->store = [];
    }
}
