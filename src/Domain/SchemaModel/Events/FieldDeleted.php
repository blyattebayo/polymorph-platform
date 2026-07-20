<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Events;

use Polymorph\Platform\Domain\SchemaModel\Events\Contracts\SchemaChangeEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие удаления поля.
 */
class FieldDeleted implements SchemaChangeEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $fieldId,
        public readonly string $fullPath,
        public readonly int $schemaId
    ) {
    }

    public function schemaId(): int
    {
        return $this->schemaId;
    }
}
