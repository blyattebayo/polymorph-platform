<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Contracts;

use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptor;

interface SchemaDescriptorProviderInterface
{
    public function forSchemaId(int $schemaId): SchemaDescriptor;
}
