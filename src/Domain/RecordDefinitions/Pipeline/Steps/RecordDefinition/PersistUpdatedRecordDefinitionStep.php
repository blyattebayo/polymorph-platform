<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\UpdateRecordDefinitionContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

final class PersistUpdatedRecordDefinitionStep extends AbstractStep
{
    public function __construct(
        private readonly RecordDefinitionRepository $recordDefinitionRepository,
    ) {
        parent::__construct(UpdateRecordDefinitionContext::class);
    }

    public function name(): string
    {
        return 'post-type.update.persist';
    }

    public function requires(): array
    {
        return [
            'input.record_definition',
            'input.payload',
        ];
    }

    public function produces(): array
    {
        return [
            'state.updated_record_definition',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var UpdateRecordDefinitionContext $context */
        $context->updatedRecordDefinition = $this->recordDefinitionRepository->update(
            $context->recordDefinition,
            $context->payload->toArray(),
        );

        return StepResult::success();
    }
}
