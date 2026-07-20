<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline\Handlers;

use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\Records\Events\RecordDeleted;
use Polymorph\Platform\Domain\Records\Pipeline\Commands\DeleteRecordCommand;
use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordId;
use Polymorph\Platform\Domain\Records\Pipeline\RecordPipelineDefinitions;
use Polymorph\Platform\PipelineCore\Observability\OperationId;
use Polymorph\Platform\PipelineCore\Runtime\TransactionalPipelineRunner;

/**
 * Handler for DeleteRecordCommand
 * Soft-deletes a record and applies graph policy
 */
final class DeleteRecordHandler
{
    public function __construct(
        private readonly TransactionalPipelineRunner $txRunner,
        private readonly RecordPipelineDefinitions $definitions,
    ) {}

    public function handle(DeleteRecordCommand $command): void
    {
        $operationId = $command->operationId
            ? OperationId::fromString($command->operationId)
            : OperationId::generate();
        $recordId = RecordId::fromInt($command->recordId);

        $context = new RecordWriteContext(
            (string) $operationId,
            $recordId,
            $command->actorId,
        );

        $definition = $this->definitions->delete();

        $this->txRunner->runInTransaction($definition, $context, $operationId);

        $before = $context->requireSnapshotBefore();
        $after = $context->requireSnapshotAfter();

        Event::dispatch(new RecordDeleted($before, $after));
    }
}
