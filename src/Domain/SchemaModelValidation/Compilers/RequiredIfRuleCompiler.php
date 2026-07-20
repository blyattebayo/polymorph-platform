<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Compilers;

use Polymorph\Platform\Domain\SchemaModelValidation\Operands\DslOperandResolver;
use Polymorph\Platform\Domain\SchemaModelValidation\Rules\ScopedRequiredIf;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptor;

final class RequiredIfRuleCompiler implements RuleCompilerInterface
{
    public function __construct(
        private readonly DslOperandResolver $operandResolver,
    ) {}

    public function handledKey(): string
    {
        return 'required_if';
    }

    public function compile(
        string $fieldFullPath,
        string $targetPath,
        array $validationRules,
        SchemaDescriptor $schema,
    ): array {
        $requiredIf = $validationRules['required_if'] ?? null;
        if (! is_array($requiredIf)) {
            return [];
        }

        $fieldOperand = is_string($requiredIf['field'] ?? null) ? $requiredIf['field'] : null;
        $otherFieldTemplate = $fieldOperand !== null && $fieldOperand !== ''
            ? $this->operandResolver->resolve($fieldOperand, $fieldFullPath, $schema)->resolved
            : null;

        if ($otherFieldTemplate === null) {
            return [];
        }

        return [
            new ScopedRequiredIf(
                $otherFieldTemplate,
                $requiredIf['value'] ?? true,
                (string) ($requiredIf['operator'] ?? '=='),
            ),
        ];
    }
}
