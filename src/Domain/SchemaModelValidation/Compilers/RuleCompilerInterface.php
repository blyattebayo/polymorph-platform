<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Compilers;

use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptor;

interface RuleCompilerInterface
{
    public function handledKey(): string;

    /**
     * @param  array<string, mixed>  $validationRules
     * @return list<object>
     */
    public function compile(
        string $fieldFullPath,
        string $targetPath,
        array $validationRules,
        SchemaDescriptor $schema,
    ): array;
}
