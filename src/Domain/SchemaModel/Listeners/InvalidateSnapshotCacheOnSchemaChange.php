<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Listeners;

use Polymorph\Platform\Domain\SchemaModel\Events\SchemaChanged;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaSnapshotService;

/**
 * Инвалидирует кэш снапшота схемы при любом изменении схемы/полей.
 *
 * Snapshot invalidation reacts to the canonical schema change event.
 * своё). Диспатч событий синхронный, поэтому кэш очищается в той же точке
 * выполнения (во время save), что и прежде.
 */
final class InvalidateSnapshotCacheOnSchemaChange
{
    public function __construct(
        private readonly SchemaSnapshotService $schemaService,
    ) {}

    public function handle(SchemaChanged $event): void
    {
        $this->schemaService->clearCacheForSchema($event->schemaId);
    }
}
