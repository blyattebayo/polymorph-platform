<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline\Handlers;

use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\Records\Events\RecordRestored;
use Polymorph\Platform\Domain\Records\Pipeline\Commands\RestoreRecordCommand;
use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordId;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordSnapshot;
use Polymorph\Platform\Domain\Records\Pipeline\RecordPipelineDefinitions;
use Polymorph\Platform\PipelineCore\Observability\OperationId;
use Polymorph\Platform\PipelineCore\Runtime\TransactionalPipelineRunner;

final class RestoreRecordHandler
{
    public function __construct(
        private readonly TransactionalPipelineRunner $txRunner,
        private readonly RecordPipelineDefinitions $definitions,
    ) {}

    public function handle(RestoreRecordCommand $command): RecordSnapshot
    {
        $operationId = $command->operationId
            ? OperationId::fromString($command->operationId)
            : OperationId::generate();

        $context = new RecordWriteContext(
            (string) $operationId,
            RecordId::fromInt($command->recordId),
            $command->actorId,
        );

        $this->txRunner->runInTransaction(
            $this->definitions->restoreWrite(),
            $context,
            $operationId,
        );

        $before = $context->requireSnapshotBefore();
        $after = $context->requireSnapshotAfter();

        Event::dispatch(new RecordRestored($before, $after));

        return $after;
    }
}
