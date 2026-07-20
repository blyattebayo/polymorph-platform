<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Events\Contracts\SchemaChangeEvent;

/**
 * Событие создания схемы.
 */
class SchemaCreated implements SchemaChangeEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SchemaModel $schema
    ) {}

    public function schemaId(): int
    {
        return (int) $this->schema->id;
    }
}
