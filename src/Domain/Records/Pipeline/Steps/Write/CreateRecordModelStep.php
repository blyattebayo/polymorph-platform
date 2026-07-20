<?php

namespace Polymorph\Platform\Domain\Records\Pipeline\Steps\Write;

use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordSnapshot;
use Polymorph\Platform\Domain\Records\Support\RecordDataNormalizer;
use Polymorph\Platform\Domain\Records\Support\RecordSystemFields;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

final class CreateRecordModelStep extends AbstractStep
{
    public function __construct()
    {
        parent::__construct(RecordWriteContext::class);
    }

    public function name(): string
    {
        return 'record.create_model';
    }

    public function requires(): array
    {
        return [
            'input.record_definition',
            'input.pending_payload',
        ];
    }

    public function produces(): array
    {
        return [
            'state.record',
            'state.snapshot_after',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var RecordWriteContext $context */
        $recordDefinitionId = (int) $context->requireRecordDefinition()->id;

        $record = new Record();
        $record->record_definition_id = $recordDefinitionId;
        $record->author_id = $context->actorId;
        $record->setAttribute(
            'data_json',
            RecordSystemFields::applyForCreate(
                RecordDataNormalizer::fromMixed($context->resolvedPayload())
            ),
        );
        $record->revision = 0;
        $record->save();

        $context->setRecord($record);
        $context->setSnapshotAfter(RecordSnapshot::fromModel($record));

        return StepResult::success();
    }
}
