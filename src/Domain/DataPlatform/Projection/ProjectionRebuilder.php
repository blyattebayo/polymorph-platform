<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Control\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Serialization\CanonicalJson;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

final class ProjectionRebuilder
{
    public function __construct(
        private readonly SchemaCatalog $schemas,
        private readonly ProjectionChangeSetBuilder $changes,
        private readonly ProjectionStore $store,
        private readonly DatabaseJson $json,
        private readonly CanonicalJson $canonicalJson,
    ) {}

    /** @return array{processed:int,changed:int} */
    public function rebuildDefinition(int $definitionId, int $batchSize = 200, bool $dryRun = false): array
    {
        $processed = 0;
        $changed = 0;
        $afterRecordId = 0;
        do {
            $batch = $this->rebuildDefinitionBatch($definitionId, $afterRecordId, $batchSize, $dryRun);
            $processed += $batch->processed;
            $changed += count($batch->changedRecordIds);
            $afterRecordId = $batch->lastRecordId;
        } while ($batch->processed > 0);

        return ['processed' => $processed, 'changed' => $changed];
    }

    public function rebuildDefinitionBatch(
        int $definitionId,
        int $afterRecordId = 0,
        int $batchSize = 200,
        bool $dryRun = false,
    ): ProjectionRebuildBatchResult {
        $limit = max(1, $batchSize);
        $recordIds = DB::table('dp_records')
            ->where('record_definition_id', $definitionId)
            ->whereNull('deleted_at')
            ->where('id', '>', $afterRecordId)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map('intval')
            ->all();

        return $this->rebuildIds($recordIds, $afterRecordId, $limit, $dryRun);
    }

    public function rebuildDependentsBatch(
        int $targetRecordId,
        int $afterSourceRecordId = 0,
        int $batchSize = 200,
    ): ProjectionRebuildBatchResult {
        $limit = max(1, $batchSize);
        $recordIds = DB::table('dp_ref_edges')
            ->where('target_record_id', $targetRecordId)
            ->where('source_record_id', '>', $afterSourceRecordId)
            ->orderBy('source_record_id')
            ->distinct()
            ->limit($limit)
            ->pluck('source_record_id')
            ->map('intval')
            ->all();

        return $this->rebuildIds($recordIds, $afterSourceRecordId, $limit, false);
    }

    /** @return array{changed:bool,expected_hash:string,actual_hash:string} */
    public function rebuildRecord(int $recordId, bool $dryRun = false): array
    {
        return DB::transaction(function () use ($recordId, $dryRun): array {
            $record = DB::table('dp_records')->where('id', $recordId)->lockForUpdate()->first();
            if ($record === null) {
                throw DataPlatformResourceNotFound::for('record', $recordId);
            }

            return $this->rebuildLockedRecord($record, $this->actual($recordId), $dryRun);
        });
    }

    private function actual(int $recordId): ProjectionChangeSet
    {
        return $this->actualMany([$recordId])[$recordId];
    }

    /** @param list<int> $recordIds @return array<int,ProjectionChangeSet> */
    private function actualMany(array $recordIds): array
    {
        $results = [];
        foreach ($recordIds as $recordId) {
            $results[$recordId] = new ProjectionChangeSet;
        }

        $refEdges = DB::table('dp_ref_edges')->whereIn('source_record_id', $recordIds)
            ->orderBy('source_record_id')->orderBy('field_id')->orderBy('occurrence')->orderBy('position')
            ->get(['source_record_id', 'field_id', 'occurrence', 'item_id', 'position', 'target_record_id', 'deletion_policy', 'projection_version']);
        foreach ($refEdges as $row) {
            $item = (array) $row;
            $recordId = (int) $item['source_record_id'];
            unset($item['source_record_id']);
            $results[$recordId]->refEdges[] = $item;
        }

        $mediaEdges = DB::table('dp_media_edges')->whereIn('source_record_id', $recordIds)
            ->orderBy('source_record_id')->orderBy('field_id')->orderBy('occurrence')->orderBy('position')
            ->get(['source_record_id', 'field_id', 'occurrence', 'item_id', 'position', 'media_id', 'attachment', 'projection_version']);
        foreach ($mediaEdges as $row) {
            $item = (array) $row;
            $recordId = (int) $item['source_record_id'];
            unset($item['source_record_id']);
            $item['attachment'] = $this->json->decodeMap($row->attachment, 'dp_media_edges.attachment');
            $results[$recordId]->mediaEdges[] = $item;
        }

        $uniqueValues = DB::table('dp_unique_values')->whereIn('record_id', $recordIds)
            ->orderBy('record_id')->orderBy('field_id')->orderBy('value_hash')
            ->get(['record_id', 'field_id', 'value_hash', 'value', 'projection_version']);
        foreach ($uniqueValues as $row) {
            $item = (array) $row;
            $recordId = (int) $item['record_id'];
            unset($item['record_id']);
            $item['value'] = $this->json->decodeValue($row->value, 'dp_unique_values.value');
            $results[$recordId]->uniqueValues[] = $item;
        }

        foreach (DB::table('dp_search_documents')->whereIn('record_id', $recordIds)->get(['record_id', 'content']) as $row) {
            $content = (string) $row->content;
            $results[(int) $row->record_id]->searchValues = $content === '' ? [] : explode("\n", $content);
        }
        foreach (DB::table('dp_display_values')->whereIn('record_id', $recordIds)->get(['record_id', 'value']) as $row) {
            $results[(int) $row->record_id]->displayValue = is_string($row->value) ? $row->value : null;
        }

        return $results;
    }

    private function hash(ProjectionChangeSet $changes): string
    {
        $sort = function (array $rows): array {
            usort($rows, fn (array $a, array $b): int => $this->canonicalJson->encode($a) <=> $this->canonicalJson->encode($b));

            return $rows;
        };
        $refEdges = $sort($changes->refEdges);
        $mediaEdges = $sort($changes->mediaEdges);
        $uniqueValues = $sort($changes->uniqueValues);
        $searchValues = $changes->searchValues;
        sort($searchValues);

        return $this->canonicalJson->hash([
            $refEdges, $mediaEdges, $uniqueValues,
            $searchValues, $changes->displayValue,
        ]);
    }

    /**
     * @param  list<FieldDefinition>|null  $fields
     * @return array{changed:bool,expected_hash:string,actual_hash:string}
     */
    private function rebuildLockedRecord(
        object $record,
        ProjectionChangeSet $actual,
        bool $dryRun,
        ?array $fields = null,
    ): array {
        $recordId = (int) $record->id;
        $versionId = (string) $record->schema_version_id;
        $expected = $this->changes->build(
            (int) $record->record_definition_id,
            $versionId,
            $this->json->decodeMap($record->data, 'dp_records.data'),
            $fields ?? $this->schemas->fields($versionId),
        );
        $expected->displayValue ??= "Record #{$recordId}";
        $expectedHash = $this->hash($expected);
        $actualHash = $this->hash($actual);
        $changed = ! hash_equals($expectedHash, $actualHash);

        if ($changed && ! $dryRun) {
            $this->store->replace($recordId, (int) $record->record_definition_id, $expected);
        }

        return ['changed' => $changed, 'expected_hash' => $expectedHash, 'actual_hash' => $actualHash];
    }

    /** @param list<int> $recordIds */
    private function rebuildIds(array $recordIds, int $fallbackLastId, int $limit, bool $dryRun): ProjectionRebuildBatchResult
    {
        $changedRecordIds = $recordIds === [] ? [] : DB::transaction(function () use ($recordIds, $dryRun): array {
            $records = DB::table('dp_records')->whereIn('id', $recordIds)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actual = $this->actualMany($recordIds);
            $fieldsByVersion = [];
            $changed = [];
            foreach ($recordIds as $recordId) {
                $record = $records->get($recordId);
                if ($record === null) {
                    throw DataPlatformResourceNotFound::for('record', $recordId);
                }
                $versionId = (string) $record->schema_version_id;
                $fields = $fieldsByVersion[$versionId] ??= $this->schemas->fields($versionId);
                $result = $this->rebuildLockedRecord($record, $actual[$recordId], $dryRun, $fields);
                if (! $result['changed']) {
                    continue;
                }
                $changed[] = $recordId;
            }

            return $changed;
        });

        return new ProjectionRebuildBatchResult(
            processed: count($recordIds),
            changedRecordIds: $changedRecordIds,
            lastRecordId: $recordIds === [] ? $fallbackLastId : max($recordIds),
            mayHaveMore: count($recordIds) === $limit,
        );
    }
}
