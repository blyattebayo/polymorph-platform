<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\ReadModel;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Polymorph\Platform\Domain\SchemaModelValidation\FieldPathBuilder;

final class SchemaSnapshotService
{
    /** @var array<int, SchemaSnapshot> */
    private array $snapshotCache = [];

    public function __construct(
        private readonly FieldPathBuilder $pathBuilder,
    ) {}

    public function snapshotForRootRecordDefinition(int $rootRecordDefinitionId): SchemaSnapshot
    {
        if (isset($this->snapshotCache[$rootRecordDefinitionId])) {
            return $this->snapshotCache[$rootRecordDefinitionId];
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
        }
    }

    public function clearCacheForRecordDefinition(int $recordDefinitionId): void
    {
        if ($recordDefinitionId > 0) {
            unset($this->snapshotCache[$recordDefinitionId]);
        }
    }
}
