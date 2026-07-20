<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Schema;

use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\SchemaRepository;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts\DeleteSchemaContext;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

final class PersistDeletedSchemaStep extends AbstractStep
{
    public function __construct(
        private readonly SchemaRepository $schemaRepository,
    ) {
        parent::__construct(DeleteSchemaContext::class);}

    public function name(): string
    {
        return 'schema-model.delete.persist';
    }

    public function requires(): array
    {
        return [
            'input.schema',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var DeleteSchemaContext $context */

        $this->schemaRepository->delete($context->schema);

        return StepResult::success();
    }

    

    
}
