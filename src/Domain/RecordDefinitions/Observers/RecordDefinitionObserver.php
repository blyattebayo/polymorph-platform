<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Observers;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionCreated;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionDeleted;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionSchemaChanged;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Observer для модели RecordDefinition.
 *
 * Обрабатывает события жизненного цикла модели.
 */
class RecordDefinitionObserver
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    /**
     * Handle the RecordDefinition "created" event.
     */
    public function created(RecordDefinition $recordDefinition): void
    {
        $this->scheduleViewRebuild($recordDefinition);

        $this->logger->event('schema.record_definition.created', [
            'id' => $recordDefinition->id,
            'name' => $recordDefinition->name,
        ]);
    }

    /**
     * Handle the RecordDefinition "updated" event.
     */
    public function updated(RecordDefinition $recordDefinition): void
    {
        if ($recordDefinition->wasChanged('display_template') || $recordDefinition->wasChanged('schema_id')) {
            $this->scheduleViewRebuild($recordDefinition);
        }

        $this->logger->event('schema.record_definition.updated', [
            'id' => $recordDefinition->id,
            'name' => $recordDefinition->name,
        ]);
    }

    /**
     * Handle the RecordDefinition "deleted" event.
     */
    public function deleted(RecordDefinition $recordDefinition): void
    {
        $this->scheduleViewDeletion((int) $recordDefinition->id);

        $this->logger->event('schema.record_definition.deleted', [
            'id' => $recordDefinition->id,
            'name' => $recordDefinition->name,
        ]);
    }

    private function scheduleViewRebuild(RecordDefinition $recordDefinition): void
    {
        $recordDefinitionId = (int) $recordDefinition->id;
        if ($recordDefinitionId <= 0) {
            return;
        }

        DB::afterCommit(function () use ($recordDefinition): void {
            if ($recordDefinition->wasRecentlyCreated) {
                Event::dispatch(new RecordDefinitionCreated($recordDefinition));

                return;
            }

            Event::dispatch(new RecordDefinitionSchemaChanged($recordDefinition));
        });
    }

    private function scheduleViewDeletion(int $recordDefinitionId): void
    {
        if ($recordDefinitionId <= 0) {
            return;
        }

        DB::afterCommit(function () use ($recordDefinitionId): void {
            Event::dispatch(new RecordDefinitionDeleted($recordDefinitionId));
        });
    }
}
