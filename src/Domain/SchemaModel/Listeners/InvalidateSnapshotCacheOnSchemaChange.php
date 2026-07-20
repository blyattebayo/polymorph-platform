<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Listeners;

use Polymorph\Platform\Domain\SchemaModel\Events\Contracts\SchemaChangeEvent;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\Contracts\SchemaSnapshotServiceInterface;

/**
 * Инвалидирует кэш снапшота схемы при любом изменении схемы/полей.
 *
 * Раньше этот side-effect инлайнился в FieldObserver/SchemaObserver — теперь
 * это листенер на общий контракт SchemaChangeEvent (каждый домен реагирует на
 * своё). Диспатч событий синхронный, поэтому кэш очищается в той же точке
 * выполнения (во время save), что и прежде.
 */
final class InvalidateSnapshotCacheOnSchemaChange
{
    public function __construct(
        private readonly SchemaSnapshotServiceInterface $schemaService,
    ) {
    }

    public function handle(SchemaChangeEvent $event): void
    {
        $this->schemaService->clearCacheForSchema($event->schemaId());
    }
}
