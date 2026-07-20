<?php

namespace Polymorph\Platform\Domain\Records\Pipeline\Handlers;

use Polymorph\Platform\Domain\Records\Events\RecordUpdated;
use Polymorph\Platform\Domain\Records\Pipeline\Commands\UpdateRecordCommand;
use Polymorph\Platform\Domain\Records\Pipeline\RecordPipelineDefinitions;
use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordId;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordSnapshot;
use Polymorph\Platform\PipelineCore\Observability\OperationId;
use Polymorph\Platform\PipelineCore\Runtime\TransactionalPipelineRunner;
use Illuminate\Support\Facades\Event;

/**
 * Handler for UpdateRecordCommand.
 * Orchestrates write pipeline only.
 */
final class UpdateRecordHandler
{
    public function __construct(
        private readonly TransactionalPipelineRunner $txRunner,
        private readonly RecordPipelineDefinitions $definitions,
    ) {}

    public function handle(UpdateRecordCommand $command): RecordSnapshot
    {
        $operationId = $command->operationId
            ? OperationId::fromString($command->operationId)
            : OperationId::generate();
        $recordId = RecordId::fromInt($command->recordId);

        $writeContext = new RecordWriteContext(
            (string) $operationId,
            $recordId,
            $command->actorId,
            $command->dataJson,
        );
        $writeContext->setRecordDefinition($command->recordDefinition);

        $definition = $this->definitions->updateWrite();

        $this->txRunner->runInTransaction($definition, $writeContext, $operationId);

        if (! $writeContext->getChangeSet()->isNoop()) {
            $before = $writeContext->requireSnapshotBefore();
            $after = $writeContext->requireSnapshotAfter();

            Event::dispatch(new RecordUpdated($before, $after));
        }

        return $writeContext->getSnapshotAfter() ?? $writeContext->getSnapshotBefore()
            ?? throw new \RuntimeException('No snapshot available after update');
    }
}
