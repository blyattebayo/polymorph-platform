<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\CreateRecordDefinitionContext;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

final class PersistCreatedRecordDefinitionStep extends AbstractStep
{
    public function __construct(
        private readonly RecordDefinitionRepository $recordDefinitionRepository,
    ) {
        parent::__construct(CreateRecordDefinitionContext::class);}

    public function name(): string
    {
        return 'post-type.create.persist';
    }

    public function requires(): array
    {
        return [
            'input.payload',
        ];
    }

    public function produces(): array
    {
        return [
            'state.created_record_definition',
        ];
    }

    

    public function run(PipelineContext $context): StepResult
    {
        /** @var CreateRecordDefinitionContext $context */
        $context->createdRecordDefinition = $this->recordDefinitionRepository->create($context->payload->toArray());

        return StepResult::success();
    }

    
}
