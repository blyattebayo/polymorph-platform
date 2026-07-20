<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline\Steps\Write;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Records\Core\Contracts\RecordRepository;
use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordSnapshot;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

/**
 * Loads an active record from DB into write context.
 * Locking is handled by PipelineCore executor on LOAD_AND_LOCK stage.
 */
final class LoadRecordStep extends AbstractStep
{
    public function __construct(
        private readonly RecordRepository $recordRepository,
    ) {
        parent::__construct(RecordWriteContext::class);
    }

    public function name(): string
    {
        return 'record.load';
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
        $recordId = $context->requireRecordId();
        $record = $this->recordRepository->findWithDefinition($recordId->value);

        if (! $record) {
            return StepResult::failure("Record {$recordId} not found");
        }

        $context->setRecord($record);
        $definition = $record->getRelation('recordDefinition');
        if (! $definition instanceof RecordDefinition) {
            return StepResult::failure("Record {$recordId->value} is missing record definition");
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
