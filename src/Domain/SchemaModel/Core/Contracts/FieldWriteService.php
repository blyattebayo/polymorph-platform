<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Contracts;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Data\CreateFieldData;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Data\UpdateFieldData;

interface FieldWriteService
{
    /**
     * @throws \DomainException
     */
    public function create(SchemaModel $schema, CreateFieldData $data): Field;

    /**
     * @throws \DomainException
     */
    public function update(SchemaModel $schema, Field $field, UpdateFieldData $data): Field;
}
