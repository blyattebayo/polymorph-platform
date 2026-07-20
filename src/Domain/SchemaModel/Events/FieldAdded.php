<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Events\Contracts\SchemaChangeEvent;

/**
 * Событие добавления поля.
 */
class FieldAdded implements SchemaChangeEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Field $field
    ) {}

    public function schemaId(): int
    {
        return (int) $this->field->schema_id;
    }
}
