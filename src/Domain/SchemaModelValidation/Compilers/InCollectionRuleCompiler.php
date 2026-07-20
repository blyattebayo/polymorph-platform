<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Compilers;

use Polymorph\Platform\Domain\Records\Support\RecordPayloadPathRegistry;
use Polymorph\Platform\Domain\SchemaModelValidation\Operands\DslOperandResolver;
use Polymorph\Platform\Domain\SchemaModelValidation\Rules\CollectionExistsIn;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptor;

final class InCollectionRuleCompiler implements RuleCompilerInterface
{
    public function __construct(
        private readonly DslOperandResolver $operandResolver,
    ) {}

    public function handledKey(): string
    {
        return 'in_collection';
    }

    public function compile(
        string $fieldFullPath,
        string $targetPath,
        array $validationRules,
        SchemaDescriptor $schema,
    ): array {
        $inCollection = $validationRules['in_collection'] ?? null;
        if (! is_array($inCollection)) {
            return [];
        }

        $source = is_string($inCollection['source'] ?? null) ? $inCollection['source'] : null;
        $inPath = is_string($inCollection['in'] ?? null) ? $inCollection['in'] : null;
        $inField = is_string($inCollection['in_field'] ?? null) ? $inCollection['in_field'] : null;

        if ($source === null || $inPath === null || $inField === null) {
            return [];
        }

        return [
            new CollectionExistsIn(
                sourceOperand: $source,
                foreignCollectionPath: $this->operandResolver->resolve($inPath, $fieldFullPath, $schema)->resolved,
                foreignField: $this->normalizeLocalFieldPath($inField),
                mode: (string) ($inCollection['mode'] ?? 'all'),
            ),
        ];
    }

    private function normalizeLocalFieldPath(string $operand): string
    {
        $normalized = $operand;

        if (str_starts_with($normalized, '$self.')) {
            $normalized = substr($normalized, strlen('$self.'));
        }

        if (str_starts_with($normalized, '$root.')) {
            $normalized = substr($normalized, strlen('$root.'));
        }

        if (RecordPayloadPathRegistry::hasDottedPrefix($normalized)) {
            $normalized = RecordPayloadPathRegistry::stripDottedPrefix($normalized);
        }

        return ltrim($normalized, '.');
    }
}
