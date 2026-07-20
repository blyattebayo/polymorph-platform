<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Combined;

use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\SchemaRepository;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts\SaveSchemaWithFieldsContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

final class PersistSchemaStep extends AbstractStep
{
    public function __construct(
        private readonly SchemaRepository $schemaRepository,
    ) {
        parent::__construct(SaveSchemaWithFieldsContext::class);
    }

    public function name(): string
    {
        return 'schema-model.combined.persist_schema';
    }

    public function requires(): array
    {
        return [
            'input.schema_payload',
        ];
    }

    public function produces(): array
    {
        return [
            'state.saved_schema',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var SaveSchemaWithFieldsContext $context */

        $context->savedSchema = $context->existingSchema === null
            ? $this->schemaRepository->create($context->schemaPayload)
            : $this->schemaRepository->update($context->existingSchema, $context->schemaPayload);

        return StepResult::success();
    }
}
