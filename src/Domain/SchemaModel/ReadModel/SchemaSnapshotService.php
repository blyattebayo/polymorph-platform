<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\ReadModel;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Polymorph\Platform\Domain\SchemaModelValidation\FieldPathBuilder;

final class SchemaSnapshotService
{
    /** @var array<int, SchemaSnapshot> */
    private array $snapshotCache = [];

    private bool $l2Enabled;

    private ?string $l2Store;

    private int $l2TtlSeconds;

    private string $l2Prefix;

    public function __construct(
        private readonly FieldPathBuilder $pathBuilder,
        ?bool $l2Enabled = null,
        ?string $l2Store = null,
        ?int $l2TtlSeconds = null,
        ?string $l2Prefix = null,
    ) {
        $this->l2Enabled = $l2Enabled ?? (bool) config('materialization.schema_cache.enabled', false);
        $this->l2Store = $l2Store ?? config('materialization.schema_cache.store');
        $this->l2TtlSeconds = max(1, (int) ($l2TtlSeconds ?? config('materialization.schema_cache.ttl_seconds', 300)));
        $this->l2Prefix = (string) ($l2Prefix ?? config('materialization.schema_cache.prefix', 'materialization:schema:'));
    }

    public function snapshotForRootRecordDefinition(int $rootRecordDefinitionId): SchemaSnapshot
    {
        if (isset($this->snapshotCache[$rootRecordDefinitionId])) {
            return $this->snapshotCache[$rootRecordDefinitionId];
        }

        $cachedL2 = $this->getL2Snapshot($rootRecordDefinitionId);
        if ($cachedL2 !== null) {
            $this->snapshotCache[$rootRecordDefinitionId] = $cachedL2;

            return $cachedL2;
        }

        $fields = $this->loadFieldsForRecordDefinition($rootRecordDefinitionId);

        $fieldsById = [];
        foreach ($fields as $field) {
            $fieldsById[$field->id] = $field;
        }

        $snapshot = new SchemaSnapshot(
            rootRecordDefinitionId: $rootRecordDefinitionId,
            fieldsById: $fieldsById,
            fullSchemaHash: $this->computeFullSchemaHash($fields)
        );

        $this->snapshotCache[$rootRecordDefinitionId] = $snapshot;
        $this->putL2Snapshot($snapshot);

        return $snapshot;
    }

    /**
     * @return SchemaFieldSnapshot[]
     */
    private function loadFieldsForRecordDefinition(int $recordDefinitionId): array
    {
        $rootRows = DB::table('fields')
            ->join('record_definitions', 'record_definitions.schema_id', '=', 'fields.schema_id')
            ->leftJoin('field_ref_constraints', 'field_ref_constraints.field_id', '=', 'fields.id')
            ->where('record_definitions.id', $recordDefinitionId)
            ->select([
                'fields.id',
                'fields.schema_id',
                'fields.name',
                'fields.type',
                'fields.cardinality',
                'fields.full_path',
                'fields.parent_id',
                'record_definitions.id as record_definition_id',
                'field_ref_constraints.allowed_record_definition_id',
            ])
            ->get();

        $allowedRecordDefinitionIds = $rootRows
            ->pluck('allowed_record_definition_id')
            ->filter(static fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $rows = collect($rootRows->all());

        if ($allowedRecordDefinitionIds !== []) {
            $referencedRows = DB::table('fields')
                ->join('record_definitions', 'record_definitions.schema_id', '=', 'fields.schema_id')
                ->whereIn('record_definitions.id', $allowedRecordDefinitionIds)
                ->select([
                    'fields.id',
                    'fields.schema_id',
                    'fields.name',
                    'fields.type',
                    'fields.cardinality',
                    'fields.full_path',
                    'fields.parent_id',
                    'record_definitions.id as record_definition_id',
                    DB::raw('NULL as allowed_record_definition_id'),
                ])
                ->get();

            $rows = $rows->concat($referencedRows->all())->unique('id')->values();
        }

        $pathCardinalitiesBySchema = [];
        foreach ($rows as $row) {
            $schemaId = (int) $row->schema_id;
            if ($schemaId <= 0) {
                continue;
            }

            if (! isset($pathCardinalitiesBySchema[$schemaId])) {
                $pathCardinalitiesBySchema[$schemaId] = [];
            }

            $pathCardinalitiesBySchema[$schemaId][(string) $row->full_path] = (string) $row->cardinality;
        }

        $allFields = [];
        foreach ($rows as $row) {
            $schemaId = (int) $row->schema_id;
            $pathCardinalities = $pathCardinalitiesBySchema[$schemaId] ?? [];
            $dataPath = $this->pathBuilder->computeDataPath(
                (string) $row->full_path,
                (string) $row->cardinality,
                $pathCardinalities,
                (string) $row->type === FieldType::JSON->value,
            );

            $allFields[] = new SchemaFieldSnapshot(
                id: (int) $row->id,
                name: (string) $row->name,
                type: (string) $row->type,
                cardinality: (string) $row->cardinality,
                dataPath: $dataPath,
                fullPath: (string) $row->full_path,
                parentId: isset($row->parent_id) ? (int) $row->parent_id : null,
                recordDefinitionId: isset($row->record_definition_id) ? (int) $row->record_definition_id : null,
                allowedRecordDefinitionId: isset($row->allowed_record_definition_id) ? (int) $row->allowed_record_definition_id : null,
            );
        }

        return $allFields;
    }

    private function computeFullSchemaHash(array $fields): string
    {
        $data = [];

        foreach ($fields as $field) {
            $data[] = [
                'id' => $field->id,
                'name' => $field->name,
                'type' => $field->type,
                'cardinality' => $field->cardinality,
                'fullPath' => $field->fullPath,
                'dataPath' => $field->dataPath,
                'parentId' => $field->parentId,
                'recordDefinitionId' => $field->recordDefinitionId,
                'allowedRecordDefinitionId' => $field->allowedRecordDefinitionId,
            ];
        }

        usort($data, fn ($a, $b) => $a['id'] <=> $b['id']);

        return hash('sha256', json_encode($data));
    }

    public function clearCacheForSchema(int $schemaId): void
    {
        if ($schemaId <= 0) {
            return;
        }

        $recordDefinitionIds = DB::table('record_definitions')
            ->where('schema_id', $schemaId)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        foreach ($recordDefinitionIds as $recordDefinitionId) {
            unset($this->snapshotCache[$recordDefinitionId]);
            $this->forgetL2Snapshot($recordDefinitionId);
        }
    }

    private function cacheRepository(): CacheRepository
    {
        if ($this->l2Store === null || $this->l2Store === '') {
            return Cache::store();
        }

        return Cache::store($this->l2Store);
    }

    private function snapshotCacheKey(int $recordDefinitionId): string
    {
        return $this->l2Prefix.'record_definition:'.$recordDefinitionId;
    }

    private function getL2Snapshot(int $recordDefinitionId): ?SchemaSnapshot
    {
        if (! $this->l2Enabled || $recordDefinitionId <= 0) {
            return null;
        }

        $payload = $this->cacheRepository()->get($this->snapshotCacheKey($recordDefinitionId));
        if (! is_array($payload)) {
            return null;
        }

        return $this->hydrateSnapshot($payload);
    }

    private function putL2Snapshot(SchemaSnapshot $snapshot): void
    {
        if (! $this->l2Enabled) {
            return;
        }

        $this->cacheRepository()->put(
            $this->snapshotCacheKey($snapshot->rootRecordDefinitionId),
            $this->snapshotToPayload($snapshot),
            $this->l2TtlSeconds,
        );
    }

    private function forgetL2Snapshot(int $recordDefinitionId): void
    {
        if (! $this->l2Enabled || $recordDefinitionId <= 0) {
            return;
        }

        $this->cacheRepository()->forget($this->snapshotCacheKey($recordDefinitionId));
    }

    /**
     * @return array{root_record_definition_id:int,full_schema_hash:string,fields_by_id:array<string, array<string, mixed>>}
     */
    private function snapshotToPayload(SchemaSnapshot $snapshot): array
    {
        $fieldsById = [];

        foreach ($snapshot->fieldsById as $fieldId => $field) {
            $fieldsById[(string) $fieldId] = [
                'id' => $field->id,
                'name' => $field->name,
                'type' => $field->type,
                'cardinality' => $field->cardinality,
                'data_path' => $field->dataPath,
                'full_path' => $field->fullPath,
                'parent_id' => $field->parentId,
                'record_definition_id' => $field->recordDefinitionId,
                'allowed_record_definition_id' => $field->allowedRecordDefinitionId,
            ];
        }

        return [
            'root_record_definition_id' => $snapshot->rootRecordDefinitionId,
            'full_schema_hash' => $snapshot->fullSchemaHash,
            'fields_by_id' => $fieldsById,
        ];
    }

    private function hydrateSnapshot(array $payload): ?SchemaSnapshot
    {
        $rootRecordDefinitionId = (int) ($payload['root_record_definition_id'] ?? 0);
        $fullSchemaHash = (string) ($payload['full_schema_hash'] ?? '');
        $fieldsPayload = $payload['fields_by_id'] ?? null;

        if ($rootRecordDefinitionId <= 0 || $fullSchemaHash === '' || ! is_array($fieldsPayload)) {
            return null;
        }

        $fieldsById = [];
        foreach ($fieldsPayload as $fieldId => $fieldData) {
            if (! is_array($fieldData)) {
                return null;
            }

            $fieldsById[(int) $fieldId] = new SchemaFieldSnapshot(
                id: (int) ($fieldData['id'] ?? 0),
                name: (string) ($fieldData['name'] ?? ''),
                type: (string) ($fieldData['type'] ?? ''),
                cardinality: (string) ($fieldData['cardinality'] ?? ''),
                dataPath: (string) ($fieldData['data_path'] ?? ''),
                fullPath: (string) ($fieldData['full_path'] ?? ''),
                parentId: isset($fieldData['parent_id']) ? (int) $fieldData['parent_id'] : null,
                recordDefinitionId: isset($fieldData['record_definition_id']) ? (int) $fieldData['record_definition_id'] : null,
                allowedRecordDefinitionId: isset($fieldData['allowed_record_definition_id']) ? (int) $fieldData['allowed_record_definition_id'] : null,
            );
        }

        return new SchemaSnapshot(
            rootRecordDefinitionId: $rootRecordDefinitionId,
            fieldsById: $fieldsById,
            fullSchemaHash: $fullSchemaHash,
        );
    }
}
