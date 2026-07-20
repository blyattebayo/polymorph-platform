<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline\Steps\Write;

use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Infrastructure\Extractors\RecordRefExtractor;
use Polymorph\Platform\Domain\Records\Pipeline\Contexts\RecordWriteContext;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordChangeSet;
use Polymorph\Platform\Domain\Records\Support\RecordSchemaResolver;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\SchemaFieldPathReadModel;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

/**
 * Builds RecordChangeSet by comparing before/after state
 * This is the source of truth for shouldRun() decisions in downstream steps
 */
final class BuildRecordChangeSetStep extends AbstractStep
{
    public function __construct(
        private readonly RecordRefExtractor $refExtractor,
        private readonly SchemaFieldPathReadModel $schemaFieldPathReadModel,
    ) {
        parent::__construct(RecordWriteContext::class);
    }

    public function name(): string
    {
        return 'record.build_changeset';
    }

    public function requires(): array
    {
        return [
            'state.record',
            'state.snapshot_before',
            'input.normalized_payload',
        ];
    }

    public function produces(): array
    {
        return [
            'state.change_set',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var RecordWriteContext $context */
        $before = $context->getSnapshotBefore();
        if (! $before) {
            throw new \LogicException('snapshotBefore must be set before building changeset');
        }

        $changedFields = $this->detectChangedFields(
            $before->dataJson,
            $context->resolvedPayload()
        );

        $record = $context->requireRecord();

        $refDiff = $this->detectRefDiff(
            $record,
            $before->dataJson,
            $context->resolvedPayload()
        );

        $isNoop = empty($changedFields) && ! $this->hasRefChanges($refDiff);

        $context->setChangeSet(new RecordChangeSet(
            isNoop: $isNoop,
            changedFields: $changedFields,
            refDiff: $refDiff,
        ));

        return StepResult::success();
    }

    public function shouldRun(PipelineContext $context): bool
    {
        /** @var RecordWriteContext $context */
        return ! $context->hasChangeSet();
    }

    private function detectChangedFields(array $before, array $after): array
    {
        $changed = [];
        $allKeys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($allKeys as $key) {
            $beforeVal = $before[$key] ?? null;
            $afterVal = $after[$key] ?? null;

            if ($beforeVal !== $afterVal) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    private function detectRefDiff(Record $record, array $before, array $after): array
    {
        $refPaths = $this->resolveRefPaths($record);
        $beforeRefs = $this->refExtractor->extractRefRecordIds($before, $refPaths);
        $afterRefs = $this->refExtractor->extractRefRecordIds($after, $refPaths);

        $beforeRefs = $this->normalizeRefIds($beforeRefs);
        $afterRefs = $this->normalizeRefIds($afterRefs);

        $added = array_diff($afterRefs, $beforeRefs);
        $removed = array_diff($beforeRefs, $afterRefs);

        return [
            'added' => array_values($added),
            'removed' => array_values($removed),
        ];
    }

    /**
     * @param  array<int|string>  $ids
     * @return int[]
     */
    private function normalizeRefIds(array $ids): array
    {
        $normalized = array_map('intval', $ids);
        $normalized = array_values(array_unique(array_filter($normalized, fn (int $id): bool => $id > 0)));
        sort($normalized);

        return $normalized;
    }

    private function hasRefChanges(array $refDiff): bool
    {
        return ! empty($refDiff['added']) || ! empty($refDiff['removed']);
    }

    /**
     * @return string[]
     */
    private function resolveRefPaths(Record $record): array
    {
        $schemaId = RecordSchemaResolver::fromRecord($record);

        if ($schemaId === null) {
            return [];
        }

        return $this->schemaFieldPathReadModel->schemaPaths($schemaId)['ref'];
    }
}
