<?php

namespace Polymorph\Platform\Domain\Records\Pipeline\Handlers;

use Polymorph\Platform\Domain\Records\Events\RecordCreated;
use Polymorph\Platform\Domain\Records\Pipeline\RecordPipelineDefinitions;
use Polymorph\Platform\Domain\Records\Pipeline\Commands\CreateRecordCommand;
use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordSnapshot;
use Polymorph\Platform\PipelineCore\Observability\OperationId;
use Polymorph\Platform\PipelineCore\Runtime\TransactionalPipelineRunner;
use Illuminate\Support\Facades\Event;

final class CreateRecordHandler
{
    public function __construct(
        private readonly TransactionalPipelineRunner $txRunner,
        private readonly RecordPipelineDefinitions $definitions,
    ) {}

    public function handle(CreateRecordCommand $command): RecordSnapshot
    {
        $operationId = $command->operationId
            ? OperationId::fromString($command->operationId)
            : OperationId::generate();

        $context = new RecordWriteContext(
            (string) $operationId,
            null,
            $command->actorId,
            $command->dataJson,
        );
        $context->setRecordDefinition($command->recordDefinition);

        $this->txRunner->runInTransaction(
            $this->definitions->createWrite(),
            $context,
            $operationId,
        );

        $snapshot = $context->getSnapshotAfter() ?? throw new \RuntimeException('Create pipeline did not produce snapshot');

        Event::dispatch(new RecordCreated(null, $snapshot));

        return $snapshot;
    }
}
