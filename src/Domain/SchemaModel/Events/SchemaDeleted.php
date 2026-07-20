<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Events;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Events\Contracts\SchemaChangeEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие удаления схемы.
 */
class SchemaDeleted implements SchemaChangeEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $schemaId,
        public readonly string $schemaCode
    ) {
    }

    public function schemaId(): int
    {
        return $this->schemaId;
    }
}
