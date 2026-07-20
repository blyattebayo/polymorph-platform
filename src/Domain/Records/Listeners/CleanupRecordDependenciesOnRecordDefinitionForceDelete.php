<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Listeners;

use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionForceDeleting;
use Polymorph\Platform\Domain\Records\Core\Contracts\RecordRepository;

final class CleanupRecordDependenciesOnRecordDefinitionForceDelete
{
    public function __construct(
        private readonly RecordRepository $records,
    ) {}

    public function handle(RecordDefinitionForceDeleting $event): void
    {
        $recordDefinitionId = (int) $event->recordDefinition->id;
        if ($recordDefinitionId <= 0) {
            return;
        }

        // Через репозиторий (владелец таблицы records), а не прямой DB::table.
        $this->records->forceDeleteByDefinition($recordDefinitionId);
    }
}
