<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline\Steps\Write;

use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordSnapshot;
use Polymorph\Platform\Domain\Records\Support\RecordDataNormalizer;
use Polymorph\Platform\Domain\Records\Support\RecordSystemFields;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

final class RestoreRecordStep extends AbstractStep
{
    public function __construct()
    {
        parent::__construct(RecordWriteContext::class);
    }

    public function name(): string
    {
        return 'record.restore';
    }

    public function requires(): array
    {
        return [
            'state.record',
        ];
    }

    public function produces(): array
    {
        return [
            'state.snapshot_after',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var RecordWriteContext $context */
        $record = $context->requireRecord();
        $payload = RecordSystemFields::normalizeForRead(
            RecordDataNormalizer::fromMixed($record->data_json)
        );

        if (! RecordSystemFields::isDeleted($payload)) {
            return StepResult::failure('Record is not deleted');
        }

        $record->restore();
        $record->setAttribute(
            'data_json',
            RecordSystemFields::applyForRestore($payload)
        );
        $record->revision++;
        $record->save();

        $context->setSnapshotAfter(RecordSnapshot::fromModel($record));

        return StepResult::success();
    }
}
