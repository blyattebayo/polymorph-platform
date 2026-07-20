<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Events;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Events\Contracts\SchemaChangeEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие обновления схемы.
 */
class SchemaUpdated implements SchemaChangeEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SchemaModel $schema,
        public readonly array $changes
    ) {
    }

    public function schemaId(): int
    {
        return (int) $this->schema->id;
    }
}
