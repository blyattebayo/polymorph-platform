<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition;

use Polymorph\Platform\Domain\DisplayViews\Services\RecordDefinitionDisplayViewSynchronizer;
use Polymorph\Platform\Domain\RecordConstraints\Services\RecordUniqueConstraintSynchronizer;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\UpdateRecordDefinitionContext;
use Polymorph\Platform\Domain\RecordIndexes\Services\RecordIndexScheduler;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

final class ApplyUpdatedRecordDefinitionDatabaseObjectsStep extends AbstractStep
{
    public function __construct(
        private readonly RecordUniqueConstraintSynchronizer $uniqueConstraints,
        private readonly RecordDefinitionDisplayViewSynchronizer $displayViews,
        private readonly RecordIndexScheduler $recordIndexes,
    ) {
        parent::__construct(UpdateRecordDefinitionContext::class);
    }

    public function name(): string
    {
        return 'record-definition.update.database-objects';
    }

    public function requires(): array
    {
        return ['state.updated_record_definition'];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var UpdateRecordDefinitionContext $context */
        $definition = $context->updatedRecordDefinition
            ?? throw new \LogicException('Updated record definition is required');

        $this->uniqueConstraints->synchronizeDefinition($definition);
        $this->displayViews->synchronize($definition);
        $this->recordIndexes->scheduleDefinition((int) $definition->id);

        return StepResult::success();
    }
}
