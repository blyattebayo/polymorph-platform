<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Handlers;

use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Commands\DeleteRecordDefinitionCommand;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\DeleteRecordDefinitionContext;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\RecordDefinitionPipelineDefinitions;
use Polymorph\Platform\PipelineCore\Observability\OperationId;
use Polymorph\Platform\PipelineCore\Runtime\TransactionalPipelineRunner;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final class DeleteRecordDefinitionHandler
{
    public function __construct(
        private readonly TransactionalPipelineRunner $txRunner,
        private readonly RecordDefinitionPipelineDefinitions $definitions,
        private readonly AppLogger $logger,
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

        $this->logger->event('schema.record_definition.deleted', [
            'id' => $command->recordDefinition->id,
            'name' => $command->recordDefinition->name,
        ]);
    }
}
