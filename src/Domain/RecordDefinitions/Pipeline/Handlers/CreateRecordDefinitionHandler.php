<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Handlers;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Commands\CreateRecordDefinitionCommand;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\CreateRecordDefinitionContext;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\RecordDefinitionPipelineDefinitions;
use Polymorph\Platform\PipelineCore\Observability\OperationId;
use Polymorph\Platform\PipelineCore\Runtime\TransactionalPipelineRunner;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final class CreateRecordDefinitionHandler
{
    public function __construct(
        private readonly TransactionalPipelineRunner $txRunner,
        private readonly RecordDefinitionPipelineDefinitions $definitions,
        private readonly AppLogger $logger,
    ) {}

    public function handle(CreateRecordDefinitionCommand $command): RecordDefinition
    {
        $operationId = OperationId::generate();

        $context = new CreateRecordDefinitionContext(payload: $command->payload);

        $this->txRunner->runInTransaction(
            $this->definitions->create(),
            $context,
            $operationId,
        );

        $createdRecordDefinition = $context->createdRecordDefinition
            ?? throw new \RuntimeException('RecordDefinition create pipeline did not produce record definition');

        $this->logger->event('schema.record_definition.created', [
            'id' => $createdRecordDefinition->id,
            'name' => $createdRecordDefinition->name,
        ]);

        return $createdRecordDefinition;
    }
}
