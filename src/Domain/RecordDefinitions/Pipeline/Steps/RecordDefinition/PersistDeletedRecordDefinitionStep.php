<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition;

use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Events\RecordDefinitionForceDeleting;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\DeleteRecordDefinitionContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

final class PersistDeletedRecordDefinitionStep extends AbstractStep
{
    public function __construct(
        private readonly RecordDefinitionRepository $recordDefinitionRepository,
    ) {
        parent::__construct(DeleteRecordDefinitionContext::class);
    }

    public function name(): string
    {
        return 'post-type.delete.persist';
    }

    public function requires(): array
    {
        return [
            'input.record_definition',
            'input.force',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var DeleteRecordDefinitionContext $context */
        if ($context->force) {
            Event::dispatch(new RecordDefinitionForceDeleting($context->recordDefinition));
            $this->recordDefinitionRepository->cleanupFieldRefConstraintsForForceDelete($context->recordDefinition);
        }

        $this->recordDefinitionRepository->delete($context->recordDefinition);

        return StepResult::success();
    }
}
