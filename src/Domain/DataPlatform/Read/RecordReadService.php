<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Read;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Access\DataAccessPolicy;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldType;
use Polymorph\Platform\Domain\DataPlatform\Media\MediaMetadataRepository;
use Polymorph\Platform\Domain\DataPlatform\Projection\DisplayTemplateRenderer;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaFieldMapper;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaStorage;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;
use Polymorph\Platform\Domain\Media\Core\Contracts\MediaIncludedProvider;

/** ACL-aware batched reader and relationship hydrator. */
final class RecordReadService
{
    public const INCLUDE_VALUES = ['records', 'media', 'relationships'];

    public function __construct(
        private readonly DataAccessPolicy $access,
        private readonly LogicalDocumentReader $logicalDocuments,
        private readonly MediaIncludedProvider $mediaIncluded,
        private readonly DisplayTemplateRenderer $displayTemplates,
        private readonly SchemaFieldMapper $schemaFields,
        private readonly DatabaseJson $json,
        private readonly RecordRowPresenter $rows,
        private readonly MediaMetadataRepository $mediaMetadata,
    ) {}

    /** @return array<string,mixed>|null */
    public function find(int $recordId, ?int $actorId, array $include = [], int $depth = 1): ?array
    {
        $hydrated = $this->hydrate([$recordId], $actorId, $include, $depth);

        return $hydrated['by_record_id'][(string) $recordId] ?? null;
    }

    /**
     * @param  list<int>  $recordIds
     * @param  list<string>  $include
     * @return array{by_record_id:array<string,array<string,mixed>>,included:array{records:array<string,array<string,mixed>>,media:array<string,array<string,mixed>>}}
     */
    public function hydrate(array $recordIds, ?int $actorId, array $include = [], int $depth = 1): array
    {
        $this->displayTemplates->beginOperation();
        $rootIds = array_values(array_unique(array_filter(array_map('intval', $recordIds), static fn (int $id): bool => $id > 0)));

        return $this->hydrateRoots($this->loadReadableRecords($rootIds, $actorId), $actorId, $include, $depth);
    }

    /**
     * Adds relationships to rows already authorized and presented by QueryPlanner.
     *
     * @param  list<array<string,mixed>>  $rows
     * @param  list<string>  $include
     * @return array{by_record_id:array<string,array<string,mixed>>,included:array{records:array<string,array<string,mixed>>,media:array<string,array<string,mixed>>}}
     */
    public function hydratePresentedRows(array $rows, ?int $actorId, array $include = [], int $depth = 1): array
    {
        $this->displayTemplates->beginOperation();
        $roots = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $roots[$id] = $row;
            }
        }

        return $this->hydrateRoots($roots, $actorId, $include, $depth);
    }

    /**
     * @param  array<int,array<string,mixed>>  $roots
     * @param  list<string>  $include
     * @return array{by_record_id:array<string,array<string,mixed>>,included:array{records:array<string,array<string,mixed>>,media:array<string,array<string,mixed>>}}
     */
    private function hydrateRoots(array $roots, ?int $actorId, array $include, int $depth): array
    {
        $maxDepth = max(0, (int) config('data_platform.hydration.max_depth'));
        if ($depth < 0 || $depth > $maxDepth) {
            throw DataPlatformBadRequest::because(
                'invalid_hydration_depth',
                "Hydration depth must be between 0 and {$maxDepth}.",
                ['depth' => $depth, 'maximum' => $maxDepth],
            );
        }
        $includeRecords = in_array('records', $include, true) || in_array('relationships', $include, true);
        $includeMedia = in_array('media', $include, true) || in_array('relationships', $include, true);

        $allRecords = $roots;
        $relationships = [];
        $includedMedia = [];
        $visited = array_fill_keys(array_keys($roots), true);
        $frontier = array_keys($roots);

        for ($level = 0; $level <= $depth && $frontier !== []; $level++) {
            $refEdges = $includeRecords
                ? $this->loadReadableRelationshipEdges('dp_ref_edges', $frontier, $allRecords, $actorId, FieldType::REF)
                : collect();
            $mediaEdges = $includeMedia
                ? $this->loadReadableRelationshipEdges('dp_media_edges', $frontier, $allRecords, $actorId, FieldType::MEDIA)
                : collect();

            $targetIds = $refEdges->pluck('target_record_id')->map('intval')->unique()->values()->all();
            $targetMeta = $this->recordMetadata($targetIds);
            $readableTargetSet = $targetIds === []
                ? []
                : array_fill_keys($this->access->readableTargetRecordIds($actorId, $targetIds), true);
            $nextTargetIds = [];
            foreach ($refEdges as $edge) {
                $sourceId = (int) $edge->source_record_id;
                $fieldId = (string) $edge->field_id;
                $targetId = (int) $edge->target_record_id;
                $meta = $targetMeta[$targetId] ?? null;
                $status = match (true) {
                    $meta === null => 'missing',
                    $meta['deleted_at'] !== null => 'deleted',
                    ! isset($readableTargetSet[$targetId]) => 'forbidden',
                    default => 'resolved',
                };
                // A relationship is emitted only when the actor may read its
                // source field. That field value already contains the target
                // identity, so `forbidden` suppresses target payload access but
                // deliberately retains the edge ID. `deleted` retains it as
                // preserve-tombstone evidence for the same reason.
                $isCycle = isset($visited[$targetId]);
                $relationships[(string) $sourceId][$fieldId][] = [
                    'kind' => 'ref',
                    'target_id' => $targetId,
                    'status' => $status,
                    'position' => (int) $edge->position,
                    'occurrence' => (string) $edge->occurrence,
                    'item_id' => $edge->item_id,
                    'cycle' => $status === 'resolved' && $isCycle,
                ];
                if ($status === 'resolved' && ! $isCycle && $level < $depth) {
                    $nextTargetIds[] = $targetId;
                }
            }

            $mediaIds = $mediaEdges->pluck('media_id')->map(static fn (mixed $id): string => (string) $id)->unique()->values()->all();
            $mediaMeta = $this->mediaMetadata->findMany($mediaIds);
            $readableMediaSet = $mediaIds === []
                ? []
                : array_fill_keys($this->access->readableMediaIds($actorId, $mediaIds), true);
            $readableMediaIds = [];
            foreach ($mediaEdges as $edge) {
                $sourceId = (int) $edge->source_record_id;
                $fieldId = (string) $edge->field_id;
                $mediaId = (string) $edge->media_id;
                $meta = $mediaMeta[$mediaId] ?? null;
                $status = match (true) {
                    $meta === null => 'missing',
                    $meta['deleted_at'] !== null => 'deleted',
                    ! isset($readableMediaSet[$mediaId]) => 'forbidden',
                    default => 'resolved',
                };
                $relationships[(string) $sourceId][$fieldId][] = [
                    'kind' => 'media',
                    'target_id' => $mediaId,
                    'status' => $status,
                    'position' => (int) $edge->position,
                    'occurrence' => (string) $edge->occurrence,
                    'item_id' => $edge->item_id,
                ];
                if ($status === 'resolved') {
                    $readableMediaIds[] = $mediaId;
                }
            }

            $requestedMediaIds = array_values(array_unique($readableMediaIds));
            if ($requestedMediaIds !== []) {
                $mediaPayloads = $this->mediaIncluded->buildIncludedByIds($requestedMediaIds);
                foreach ($requestedMediaIds as $mediaId) {
                    if (is_array($mediaPayloads->{$mediaId} ?? null)) {
                        $includedMedia[$mediaId] = $mediaPayloads->{$mediaId};
                    }
                }
            }
            $next = $this->loadReadableRecords(array_values(array_unique($nextTargetIds)), $actorId);
            foreach ($next as $id => $record) {
                $allRecords[$id] = $record;
                $visited[$id] = true;
            }
            $frontier = array_keys($next);
        }

        $byRecordId = [];
        foreach ($roots as $id => $record) {
            $byRecordId[(string) $id] = [
                ...$record,
                'relationships' => $relationships[(string) $id] ?? [],
            ];
        }
        $includedRecords = [];
        foreach ($allRecords as $id => $record) {
            if (! isset($roots[$id])) {
                $includedRecords[(string) $id] = [
                    ...$record,
                    'relationships' => $relationships[(string) $id] ?? [],
                ];
            }
        }

        return [
            'by_record_id' => $byRecordId,
            'included' => ['records' => $includedRecords, 'media' => $includedMedia],
        ];
    }

    /**
     * Filters already loaded query rows through the same field ACL runtime.
     *
     * @param  list<array<string,mixed>>  $rows
     * @return list<array<string,mixed>>
     */
    public function presentQueryRows(array $rows, ?int $actorId): array
    {
        $this->displayTemplates->beginOperation();
        if ($rows === []) {
            return [];
        }
        $logicalDocuments = $this->logicalDocuments->currentMany(array_map(static fn (array $row): array => [
            'definition_id' => (int) $row['record_definition_id'],
            'stored_version_id' => (string) $row['schema_version_id'],
            'document' => is_array($row['data']) ? $row['data'] : [],
        ], $rows));
        $versions = [];
        foreach ($rows as $index => $row) {
            $versions[] = (string) $row['schema_version_id'];
            $versions[] = $logicalDocuments[$index]['logical_schema_version_id'];
        }
        $fields = $this->fieldsForVersions(array_values(array_unique($versions)));
        $displayValues = DB::table('dp_display_values')
            ->whereIn('record_id', array_map(static fn (array $row): int => (int) $row['id'], $rows))
            ->pluck('value', 'record_id')->all();
        $displaySources = [];
        foreach ($rows as $row) {
            $displaySources[(int) $row['id']] = (int) $row['record_definition_id'];
        }
        $this->displayTemplates->primeTargets($displaySources, $actorId, $this->access);

        $presented = [];
        foreach ($rows as $index => $row) {
            $definitionId = (int) $row['record_definition_id'];
            $logical = $logicalDocuments[$index];
            $logicalFields = $fields[$logical['logical_schema_version_id']] ?? [];
            $row['data'] = $this->filterDocument(
                $logical['document'],
                $logicalFields,
                $actorId,
                $definitionId,
            );
            $row['logical_schema_version_id'] = $logical['logical_schema_version_id'];
            $row['display_value'] = $this->displayTemplates->canExpose(
                $definitionId,
                $logical['logical_schema_version_id'],
                $logical['document'],
                $actorId,
                $this->access,
            ) ? ($displayValues[(int) $row['id']] ?? 'Record #'.$row['id']) : 'Record #'.$row['id'];

            $presented[] = $row;
        }

        return $presented;
    }

    /**
     * Applies the read-side field policy to a document returned by a write.
     * Persistence results intentionally retain the full normalized document;
     * SDK/transport presenters must not expose fields the actor cannot read.
     *
     * @param  array<string,mixed>  $document
     * @return array<string,mixed>
     */
    public function presentWriteDocument(
        int $definitionId,
        string $schemaVersionId,
        array $document,
        ?int $actorId,
    ): array {
        $fields = $this->fieldsForVersions([$schemaVersionId])[$schemaVersionId] ?? [];

        return $this->filterDocument($document, $fields, $actorId, $definitionId);
    }

    /** @param list<int> $ids @return array<int,array<string,mixed>> */
    private function loadReadableRecords(array $ids, ?int $actorId): array
    {
        if ($ids === []) {
            return [];
        }
        $meta = $this->recordMetadata($ids);
        $candidates = [];
        foreach ($meta as $id => $row) {
            if ($row['deleted_at'] === null
                && $this->access->canReadDefinition($actorId, (int) $row['record_definition_id'])) {
                $candidates[] = $id;
            }
        }
        $allowed = $this->access->readableTargetRecordIds($actorId, $candidates);
        if ($allowed === []) {
            return [];
        }

        $rows = DB::table('dp_records as record')
            ->leftJoin('dp_display_values as display', 'display.record_id', '=', 'record.id')
            ->whereIn('record.id', $allowed)->whereNull('record.deleted_at')
            ->get(['record.*', 'display.value as display_value']);
        $rowObjects = $rows->values()->all();
        $presentedRows = array_map($this->rows->present(...), $rowObjects);
        $logicalDocuments = $this->logicalDocuments->currentMany(array_map(static fn (array $row): array => [
            'definition_id' => $row['record_definition_id'],
            'stored_version_id' => $row['schema_version_id'],
            'document' => $row['data'],
        ], $presentedRows));
        $versions = [];
        foreach ($presentedRows as $index => $row) {
            $versions[] = $row['schema_version_id'];
            $versions[] = $logicalDocuments[$index]['logical_schema_version_id'];
        }
        $fields = $this->fieldsForVersions(array_values(array_unique($versions)));
        $displaySources = [];
        foreach ($presentedRows as $row) {
            $displaySources[$row['id']] = $row['record_definition_id'];
        }
        $this->displayTemplates->primeTargets($displaySources, $actorId, $this->access);
        $result = [];
        foreach ($presentedRows as $index => $presented) {
            $row = $rowObjects[$index];
            $id = $presented['id'];
            $definitionId = $presented['record_definition_id'];
            $versionId = $presented['schema_version_id'];
            $logical = $logicalDocuments[$index];
            $logicalVersionId = $logical['logical_schema_version_id'];
            $result[$id] = [
                'id' => $id,
                'record_definition_id' => $definitionId,
                'schema_version_id' => $versionId,
                'logical_schema_version_id' => $logicalVersionId,
                'revision' => $presented['revision'],
                'data' => $this->filterDocument($logical['document'], $fields[$logicalVersionId] ?? [], $actorId, $definitionId),
                'display_value' => $this->displayTemplates->canExpose(
                    $definitionId,
                    $logicalVersionId,
                    $logical['document'],
                    $actorId,
                    $this->access,
                ) && is_string($row->display_value) ? $row->display_value : "Record #{$id}",
                'author_id' => $presented['author_id'],
                'created_at' => $presented['created_at'],
                'updated_at' => $presented['updated_at'],
            ];
        }

        return $result;
    }

    /**
     * Applies source-field ACL in SQL before any target identity is loaded.
     *
     * @param  list<int>  $sourceIds
     * @param  array<int,array<string,mixed>>  $records
     * @return Collection<int,\stdClass>
     */
    private function loadReadableRelationshipEdges(
        string $table,
        array $sourceIds,
        array $records,
        ?int $actorId,
        FieldType $type,
    ): Collection {
        $versionIds = [];
        foreach ($sourceIds as $sourceId) {
            $versionId = $records[$sourceId]['logical_schema_version_id'] ?? null;
            if (is_string($versionId) && $versionId !== '') {
                $versionIds[] = $versionId;
            }
        }
        $fieldsByVersion = $this->fieldsForVersions(array_values(array_unique($versionIds)));
        $groups = [];
        foreach ($sourceIds as $sourceId) {
            $record = $records[$sourceId] ?? null;
            if (! is_array($record)) {
                continue;
            }
            $definitionId = (int) $record['record_definition_id'];
            $versionId = (string) $record['logical_schema_version_id'];
            $fieldIds = [];
            foreach ($fieldsByVersion[$versionId] ?? [] as $field) {
                if ($field->type === $type && $this->access->canReadField($actorId, $definitionId, $field)) {
                    $fieldIds[] = $field->id;
                }
            }
            sort($fieldIds);
            if ($fieldIds === []) {
                continue;
            }
            $key = implode('|', $fieldIds);
            $groups[$key]['field_ids'] = $fieldIds;
            $groups[$key]['source_ids'][] = $sourceId;
        }
        if ($groups === []) {
            return collect();
        }

        return DB::table($table)
            ->where(function (Builder $scope) use ($groups): void {
                foreach ($groups as $group) {
                    $scope->orWhere(function (Builder $allowed) use ($group): void {
                        $allowed->whereIn('source_record_id', $group['source_ids'])
                            ->whereIn('field_id', $group['field_ids']);
                    });
                }
            })
            ->orderBy('source_record_id')
            ->orderBy('field_id')
            ->orderBy('position')
            ->get();
    }

    /** @param list<int> $ids @return array<int,array<string,mixed>> */
    private function recordMetadata(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('dp_records')->whereIn('id', $ids)
            ->get(['id', 'record_definition_id', 'schema_version_id', 'revision', 'deleted_at'])
            ->mapWithKeys(static fn (object $row): array => [(int) $row->id => (array) $row])
            ->all();
    }

    /** @param list<string> $versionIds @return array<string,list<FieldDefinition>> */
    private function fieldsForVersions(array $versionIds): array
    {
        if ($versionIds === []) {
            return [];
        }
        $result = [];
        $rows = SchemaStorage::orderedFields(
            DB::table('dp_schema_fields')->whereIn('schema_version_id', $versionIds),
        )->get();
        foreach ($rows->groupBy('schema_version_id') as $versionId => $versionRows) {
            $result[(string) $versionId] = $this->schemaFields->fromRows($versionRows);
        }

        return $result;
    }

    /** @param list<FieldDefinition> $fields @return array<string,mixed> */
    private function filterDocument(array $document, array $fields, ?int $actorId, int $definitionId): array
    {
        $readable = [];
        $known = [];
        foreach ($fields as $field) {
            $known[$field->path] = true;
            if ($this->access->canReadField($actorId, $definitionId, $field)) {
                $readable[$field->path] = true;
            }
        }

        return $this->filterNode($document, '', $known, $readable);
    }

    /**
     * @param  array<string,true>  $known
     * @param  array<string,true>  $readable
     * @return array<string,mixed>
     */
    private function filterNode(array $node, string $parentPath, array $known, array $readable): array
    {
        $result = [];
        foreach ($node as $key => $value) {
            if ($key === '_item_id') {
                $result[$key] = $value;

                continue;
            }
            $path = $parentPath === '' ? (string) $key : $parentPath.'.'.$key;
            $hasChildren = false;
            $hasReadableChildren = false;
            foreach ($known as $candidate => $_) {
                if (str_starts_with($candidate, $path.'.')) {
                    $hasChildren = true;
                    $hasReadableChildren = $hasReadableChildren || isset($readable[$candidate]);
                }
            }
            if (isset($readable[$path]) && ! $hasChildren) {
                $result[$key] = $value;

                continue;
            }
            if (! $hasReadableChildren || ! is_array($value)) {
                continue;
            }
            if (array_is_list($value)) {
                $result[$key] = array_map(
                    fn (mixed $item): mixed => is_array($item) ? $this->filterNode($item, $path, $known, $readable) : $item,
                    $value,
                );
            } else {
                $result[$key] = $this->filterNode($value, $path, $known, $readable);
            }
        }

        return $result;
    }
}
