<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Handlers;

use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Commands\DeleteRecordDefinitionCommand;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\DeleteRecordDefinitionContext;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\RecordDefinitionPipelineDefinitions;
use Polymorph\Platform\PipelineCore\Observability\OperationId;
use Polymorph\Platform\PipelineCore\Runtime\TransactionalPipelineRunner;

final class DeleteRecordDefinitionHandler
{
    public function __construct(
        private readonly TransactionalPipelineRunner $txRunner,
        private readonly RecordDefinitionPipelineDefinitions $definitions,
    ) {}

    public function handle(DeleteRecordDefinitionCommand $command): void
    {
        $operationId = OperationId::generate();

        $context = new DeleteRecordDefinitionContext(
            recordDefinition: $command->recordDefinition,
            force: $command->force,
        );

        $this->txRunner->runInTransaction(
            $this->definitions->delete(),
            $context,
            $operationId,
        );
    }
}
