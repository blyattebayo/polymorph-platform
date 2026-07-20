<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Handlers;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Commands\UpdateRecordDefinitionCommand;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\UpdateRecordDefinitionContext;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\RecordDefinitionPipelineDefinitions;
use Polymorph\Platform\PipelineCore\Observability\OperationId;
use Polymorph\Platform\PipelineCore\Runtime\TransactionalPipelineRunner;

final class UpdateRecordDefinitionHandler
{
    public function __construct(
        private readonly TransactionalPipelineRunner $txRunner,
        private readonly RecordDefinitionPipelineDefinitions $definitions,
    ) {}

    public function handle(UpdateRecordDefinitionCommand $command): RecordDefinition
    {
        $operationId = OperationId::generate();

        $context = new UpdateRecordDefinitionContext(
            recordDefinition: $command->recordDefinition,
            payload: $command->payload,
        );

        $this->txRunner->runInTransaction(
            $this->definitions->update(),
            $context,
            $operationId,
        );

        $updatedRecordDefinition = $context->updatedRecordDefinition
            ?? throw new \RuntimeException('RecordDefinition update pipeline did not produce record definition');

        return $updatedRecordDefinition;
    }

}
