<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Handlers;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Commands\SaveSchemaWithFieldsCommand;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts\SaveSchemaWithFieldsContext;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\SchemaPipelineDefinitions;
use Polymorph\Platform\PipelineCore\Observability\OperationId;
use Polymorph\Platform\PipelineCore\Runtime\TransactionalPipelineRunner;

final class SaveSchemaWithFieldsHandler
{
    public function __construct(
        private readonly TransactionalPipelineRunner $txRunner,
        private readonly SchemaPipelineDefinitions $definitions,
    ) {}

    public function handle(SaveSchemaWithFieldsCommand $command): SchemaModel
    {
        $operationId = $command->operationId
            ? OperationId::fromString($command->operationId)
            : OperationId::generate();

        $context = new SaveSchemaWithFieldsContext(
            schemaPayload: $command->schemaPayload,
            fieldsUpsert: $command->fieldsUpsert,
            fieldsDelete: $command->fieldsDelete,
            existingSchema: $command->schema,
            operationId: (string) $operationId,
        );

        $this->txRunner->runInTransaction(
            $this->definitions->saveWithFields(),
            $context,
            $operationId,
        );

        return $context->savedSchema
            ?? throw new \RuntimeException('Schema save_with_fields pipeline did not produce schema');
    }
}
