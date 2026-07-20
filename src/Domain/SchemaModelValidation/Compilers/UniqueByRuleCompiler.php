<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Compilers;

use Polymorph\Platform\Domain\SchemaModelValidation\Rules\UniqueByKeys;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptor;

final class UniqueByRuleCompiler implements RuleCompilerInterface
{
    public function handledKey(): string
    {
        return 'unique_by';
    }

    public function compile(
        string $fieldFullPath,
        string $targetPath,
        array $validationRules,
        SchemaDescriptor $schema,
    ): array {
        $uniqueBy = $validationRules['unique_by'] ?? null;
        if (! is_array($uniqueBy) && ! is_string($uniqueBy)) {
            return [];
        }

        $keys = $this->normalizeUniqueByKeys($uniqueBy);
        if ($keys === []) {
            return [];
        }

        return [new UniqueByKeys($keys)];
    }

    /**
     * @param  array<string, mixed>|string  $config
     * @return list<string>
     */
    private function normalizeUniqueByKeys(array|string $config): array
    {
        if (is_string($config)) {
            return [$config];
        }

        $rawKeys = $config['keys'] ?? $config;

        if (! is_array($rawKeys)) {
            return [];
        }

        $keys = [];
        foreach ($rawKeys as $key) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $keys[] = $key;
        }

        return array_values(array_unique($keys));
    }
}
