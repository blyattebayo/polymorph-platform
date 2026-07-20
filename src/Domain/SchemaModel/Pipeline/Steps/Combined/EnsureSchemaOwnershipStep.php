<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Combined;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts\SaveSchemaWithFieldsContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnershipService;
use Polymorph\Platform\SharedKernel\Ownership\ResourceType;

final class EnsureSchemaOwnershipStep extends AbstractStep
{
    public function __construct(
        private readonly ResourceOwnershipService $ownershipService,
    ) {
        parent::__construct(SaveSchemaWithFieldsContext::class);
    }

    public function name(): string
    {
        return 'schema-model.combined.ensure_ownership';
    }

    public function requires(): array
    {
        return [
            'state.saved_schema',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var SaveSchemaWithFieldsContext $context */
        if (! $context->savedSchema instanceof SchemaModel) {
            throw new \RuntimeException('Schema ownership step requires saved schema.');
        }

        $this->ownershipService->ensurePlatformOwner(ResourceType::SCHEMA, (int) $context->savedSchema->id);

        return StepResult::success();
    }
}
