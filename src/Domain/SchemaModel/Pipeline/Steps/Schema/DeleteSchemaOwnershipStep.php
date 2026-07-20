<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Schema;

use Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts\DeleteSchemaContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnershipService;
use Polymorph\Platform\SharedKernel\Ownership\ResourceType;

final class DeleteSchemaOwnershipStep extends AbstractStep
{
    public function __construct(
        private readonly ResourceOwnershipService $ownershipService,
    ) {
        parent::__construct(DeleteSchemaContext::class);
    }

    public function name(): string
    {
        return 'schema-model.delete.delete_ownership';
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
        $this->ownershipService->delete(ResourceType::SCHEMA, (int) $context->schema->id);

        return StepResult::success();
    }
}
