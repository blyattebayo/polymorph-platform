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

/**
 * Applies pending changes to a record and persists with revision++
 */
final class ApplyAndPersistStep extends AbstractStep
{
    public function __construct()
    {
        parent::__construct(RecordWriteContext::class);
    }

    public function name(): string
    {
        return 'record.apply_and_persist';
    }

    public function requires(): array
    {
        return [
            'state.record',
            'state.change_set',
            'input.normalized_payload',
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
        $changeSet = $context->getChangeSet();

        if ($changeSet->isNoop()) {
            return StepResult::success(); // No-op, skip persistence
        }

        // Apply changes
        $updatedDataJson = RecordSystemFields::applyForUpdate(
            RecordDataNormalizer::fromMixed($context->resolvedPayload()),
            RecordDataNormalizer::fromMixed($record->data_json),
        );

        $record->setAttribute('data_json', $updatedDataJson);
        $record->revision++;

        // Save to DB
        $record->save();

        // Update snapshot
        $context->setSnapshotAfter(RecordSnapshot::fromModel($record));

        return StepResult::success();
    }
}
