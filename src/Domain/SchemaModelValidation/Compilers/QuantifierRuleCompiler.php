<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Compilers;

use Polymorph\Platform\Domain\SchemaModelValidation\Operands\DslOperandResolver;
use Polymorph\Platform\Domain\SchemaModelValidation\Rules\CollectionQuantifier;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptor;

final class QuantifierRuleCompiler implements RuleCompilerInterface
{
    public function __construct(
        private readonly DslOperandResolver $operandResolver,
    ) {}

    public function handledKey(): string
    {
        return 'quantifier';
    }

    public function compile(
        string $fieldFullPath,
        string $targetPath,
        array $validationRules,
        SchemaDescriptor $schema,
    ): array {
        $quantifier = $validationRules['quantifier'] ?? null;
        if (! is_array($quantifier)) {
            return [];
        }

        $leftOperand = is_string($quantifier['left'] ?? null) ? $quantifier['left'] : null;
        $rightOperand = is_string($quantifier['right'] ?? null) ? $quantifier['right'] : null;

        if ($leftOperand === null) {
            return [];
        }

        return [
            new CollectionQuantifier(
                quantifier: (string) ($quantifier['type'] ?? 'all'),
                leftOperand: $this->compileCollectionOperand($leftOperand, $fieldFullPath, $schema),
                operator: (string) ($quantifier['operator'] ?? '=='),
                rightOperandTemplate: $rightOperand !== null
                    ? $this->compileCollectionOperand($rightOperand, $fieldFullPath, $schema)
                    : null,
                rightConstantValue: $quantifier['value'] ?? null,
                countGte: (int) ($quantifier['count_gte'] ?? 1),
            ),
        ];
    }

    private function compileCollectionOperand(string $operand, string $fieldFullPath, SchemaDescriptor $schema): string
    {
        if (str_starts_with($operand, '$self.')) {
            return $operand;
        }

        return $this->operandResolver->resolve($operand, $fieldFullPath, $schema)->resolved;
    }
}
