<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Handlers;

use Polymorph\Platform\Domain\SchemaModel\Pipeline\Commands\DeleteSchemaCommand;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts\DeleteSchemaContext;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\SchemaPipelineDefinitions;
use Polymorph\Platform\PipelineCore\Observability\OperationId;
use Polymorph\Platform\PipelineCore\Runtime\TransactionalPipelineRunner;

final class DeleteSchemaHandler
{
    public function __construct(
        private readonly TransactionalPipelineRunner $txRunner,
        private readonly SchemaPipelineDefinitions $definitions,
    ) {}

    public function handle(DeleteSchemaCommand $command): void
    {
        $operationId = $command->operationId
            ? OperationId::fromString($command->operationId)
            : OperationId::generate();

        $context = new DeleteSchemaContext(
            schema: $command->schema,
        );

        $this->txRunner->runInTransaction(
            $this->definitions->delete(),
            $context,
            $operationId,
        );
    }
}
