<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Listeners;

use Polymorph\Platform\Domain\Materialization\Services\RecordDefinitionViewManager;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionCreated;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionDeleted;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionSchemaChanged;

final class SyncRecordDefinitionDisplayView
{
    public function __construct(
        private readonly RecordDefinitionViewManager $viewManager,
    ) {
    }

    public function handleRecordDefinitionCreated(RecordDefinitionCreated $event): void
    {
        $this->viewManager->ensureViewForRecordDefinition($event->recordDefinition);
    }

    public function handleRecordDefinitionSchemaChanged(RecordDefinitionSchemaChanged $event): void
    {
        $this->viewManager->ensureViewForRecordDefinition($event->recordDefinition);
    }

    public function handleRecordDefinitionDeleted(RecordDefinitionDeleted $event): void
    {
        if ($event->recordDefinitionId <= 0) {
            return;
        }

        $this->viewManager->dropViewForRecordDefinitionId($event->recordDefinitionId);
    }
}
