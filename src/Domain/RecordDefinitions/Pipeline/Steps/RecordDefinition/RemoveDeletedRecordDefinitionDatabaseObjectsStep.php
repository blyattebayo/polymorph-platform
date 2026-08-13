<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition;

use Polymorph\Platform\Domain\DisplayViews\Services\RecordDefinitionDisplayViewSynchronizer;
use Polymorph\Platform\Domain\RecordConstraints\Services\RecordUniqueConstraintSynchronizer;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\DeleteRecordDefinitionContext;
use Polymorph\Platform\Domain\RecordIndexes\Services\RecordIndexScheduler;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

final class RemoveDeletedRecordDefinitionDatabaseObjectsStep extends AbstractStep
{
    public function __construct(
        private readonly RecordUniqueConstraintSynchronizer $uniqueConstraints,
        private readonly RecordDefinitionDisplayViewSynchronizer $displayViews,
        private readonly RecordIndexScheduler $recordIndexes,
    ) {
        parent::__construct(DeleteRecordDefinitionContext::class);
    }

    public function name(): string
    {
        return 'record-definition.delete.database-objects';
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var DeleteRecordDefinitionContext $context */
        $definitionId = (int) $context->recordDefinition->id;
        $this->uniqueConstraints->dropDefinition($definitionId);
        $this->displayViews->drop($definitionId);
        $this->recordIndexes->scheduleDefinition($definitionId);

        return StepResult::success();
    }
}
