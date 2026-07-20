<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition;

use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\DeleteRecordDefinitionContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnershipService;
use Polymorph\Platform\SharedKernel\Ownership\ResourceType;

final class DeleteRecordDefinitionOwnershipStep extends AbstractStep
{
    public function __construct(
        private readonly ResourceOwnershipService $ownershipService,
    ) {
        parent::__construct(DeleteRecordDefinitionContext::class);
    }

    public function name(): string
    {
        return 'record_definition.delete.delete_ownership';
    }

    public function requires(): array
    {
        return [
            'input.record_definition',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var DeleteRecordDefinitionContext $context */
        $this->ownershipService->delete(ResourceType::RECORD_DEFINITION, (int) $context->recordDefinition->id);

        return StepResult::success();
    }
}
