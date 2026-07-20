<?php

namespace Polymorph\Platform\Domain\Records\Pipeline\Steps\Write;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Records\Core\Contracts\RecordRepository;
use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordSnapshot;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

/**
 * Loads a record including trashed records into write context.
 * Locking is handled by PipelineCore executor on LOAD_AND_LOCK stage.
 */
final class LoadTrashedRecordStep extends AbstractStep
{
    public function __construct(
        private readonly RecordRepository $recordRepository,
    ) {
        parent::__construct(RecordWriteContext::class);
    }

    public function name(): string
    {
        return 'record.load_trashed';
    }

    public function requires(): array
    {
        return [
            'input.record_id',
        ];
    }

    public function produces(): array
    {
        return [
            'state.record',
            'state.record_definition',
            'state.snapshot_before',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var RecordWriteContext $context */
        $record = $this->recordRepository->findTrashed($context->requireRecordId()->value);
        if ($record === null) {
            return StepResult::failure('Record not found');
        }

        $context->setRecord($record);
        $definition = $record->getRelation('recordDefinition');
        if (! $definition instanceof RecordDefinition) {
            return StepResult::failure('Record is missing record definition');
        }

        $context->setRecordDefinition($definition);
        $context->setSnapshotBefore(RecordSnapshot::fromModel($record));

        return StepResult::success();
    }

    public function shouldRun(PipelineContext $context): bool
    {
        /** @var RecordWriteContext $context */

        return ! $context->hasRecord();
    }
}
