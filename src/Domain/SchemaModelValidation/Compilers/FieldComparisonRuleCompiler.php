<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Compilers;

use Polymorph\Platform\Domain\SchemaModelValidation\Operands\DslOperandResolver;
use Polymorph\Platform\Domain\SchemaModelValidation\Rules\ScopedFieldComparison;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptor;

final class FieldComparisonRuleCompiler implements RuleCompilerInterface
{
    public function __construct(
        private readonly DslOperandResolver $operandResolver,
    ) {}

    public function handledKey(): string
    {
        return 'field_comparison';
    }

    public function compile(
        string $fieldFullPath,
        string $targetPath,
        array $validationRules,
        SchemaDescriptor $schema,
    ): array {
        $fieldComparison = $validationRules['field_comparison'] ?? null;
        if (! is_array($fieldComparison)) {
            return [];
        }

        $operator = (string) ($fieldComparison['operator'] ?? '>=');
        $constantValue = $fieldComparison['value'] ?? null;
        $fieldOperand = is_string($fieldComparison['field'] ?? null) ? $fieldComparison['field'] : null;
        $otherFieldTemplate = $fieldOperand !== null && $fieldOperand !== ''
            ? $this->operandResolver->resolve($fieldOperand, $fieldFullPath, $schema)->resolved
            : null;

        if ($otherFieldTemplate === null && $constantValue === null) {
            return [];
        }

        return [
            new ScopedFieldComparison(
                $operator,
                $otherFieldTemplate ?? '',
                $constantValue,
            ),
        ];
    }
}
