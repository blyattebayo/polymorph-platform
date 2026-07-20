<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Contracts;

use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptor;

interface SchemaValidationRulesEngineInterface
{
    /**
     * @return array<string, array<int, string|object>>
     */
    public function buildLaravelRulesForDescriptor(SchemaDescriptor $schemaDescriptor): array;
}
