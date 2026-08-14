<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Maps control-plane storage into the public HTTP representation. */
final class DataControlReadModel
{
    public function __construct(private readonly DatabaseJson $json) {}

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        return $this->definitionQuery()->orderBy('definition.id')->get()
            ->map(fn (object $definition): array => $this->mapDefinition($definition, false))
            ->all();
    }

    /** @return array<string, mixed> */
    public function definition(int $definitionId, bool $withVersions = true): array
    {
        $row = $this->definitionQuery()->where('definition.id', $definitionId)->first();
        if ($row === null) {
            throw DataPlatformResourceNotFound::for('record-definition', $definitionId);
        }

        return $this->mapDefinition($row, $withVersions);
    }

    /** @return array<string, mixed> */
    public function version(string $schemaVersionId): array
    {
        $row = DB::table('dp_schema_versions')->where('id', $schemaVersionId)->first();
        if ($row === null) {
            throw DataPlatformResourceNotFound::for('schema-version', $schemaVersionId);
        }
        $fields = DB::table('dp_schema_fields')
            ->where('schema_version_id', $schemaVersionId)
            ->orderBy('position')
            ->orderBy('path')
            ->get()
            ->all();

        return $this->mapVersion($row, $fields);
    }

    /** @return array<string, mixed> */
    public function migrationPlan(string $planId): array
    {
        $row = DB::table('dp_schema_migration_plans')->where('id', $planId)->first();
        if ($row === null) {
            throw DataPlatformResourceNotFound::for('migration-plan', $planId);
        }

        return [
            'id' => (string) $row->id,
            'record_definition_id' => (int) $row->record_definition_id,
            'from_schema_version_id' => (string) $row->from_schema_version_id,
            'to_schema_version_id' => (string) $row->to_schema_version_id,
            'classification' => (string) $row->classification,
            'state' => (string) $row->state,
            'operations' => $this->json->decodeList($row->operations, 'dp_schema_migration_plans.operations'),
            'checkpoint' => $row->checkpoint === null
                ? null
                : $this->json->decodeMap($row->checkpoint, 'dp_schema_migration_plans.checkpoint'),
            'invalid_records' => $this->json->decodeList($row->invalid_records, 'dp_schema_migration_plans.invalid_records'),
            'processed_count' => (int) $row->processed_count,
            'failed_count' => (int) $row->failed_count,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    private function mapDefinition(object $row, bool $withVersions): array
    {
        $definitionId = (int) $row->id;
        $result = [
            'id' => $definitionId,
            'code' => (string) $row->code,
            'name' => (string) $row->name,
            'description' => $row->description === null ? null : (string) $row->description,
            'metadata' => $row->metadata === null
                ? null
                : $this->json->decodeMap($row->metadata, 'dp_record_definitions.metadata'),
            'current_schema_version_id' => $row->current_schema_version_id === null
                ? null
                : (string) $row->current_schema_version_id,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
            'fields_count' => (int) $row->fields_count,
            'records_count' => (int) $row->records_count,
        ];
        if (! $withVersions) {
            return $result;
        }

        $versions = DB::table('dp_schema_versions')
            ->where('record_definition_id', $definitionId)
            ->orderByDesc('version')
            ->get();
        $versionIds = $versions->pluck('id')->map('strval')->all();
        $fields = DB::table('dp_schema_fields')
            ->whereIn('schema_version_id', $versionIds)
            ->orderBy('position')
            ->orderBy('path')
            ->get()
            ->groupBy('schema_version_id');
        $result['schema_versions'] = $versions->map(
            fn (object $version): array => $this->mapVersion(
                $version,
                $fields->get((string) $version->id, collect())->all(),
            ),
        )->all();

        return $result;
    }

    /** @param list<object> $fields @return array<string, mixed> */
    private function mapVersion(object $row, array $fields): array
    {
        return [
            'id' => (string) $row->id,
            'record_definition_id' => (int) $row->record_definition_id,
            'version' => (int) $row->version,
            'state' => (string) $row->state,
            'previous_version_id' => $row->previous_version_id === null ? null : (string) $row->previous_version_id,
            'checksum' => $row->checksum === null ? null : (string) $row->checksum,
            'metadata' => $row->metadata === null
                ? null
                : $this->json->decodeMap($row->metadata, 'dp_schema_versions.metadata'),
            'validated_at' => $row->validated_at === null ? null : (string) $row->validated_at,
            'published_at' => $row->published_at === null ? null : (string) $row->published_at,
            'archived_at' => $row->archived_at === null ? null : (string) $row->archived_at,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
            'fields' => array_map(fn (object $field): array => $this->mapField($field), $fields),
        ];
    }

    /** @return array<string,mixed> */
    private function mapField(object $field): array
    {
        return [
            'id' => (int) $field->id,
            'schema_version_id' => (string) $field->schema_version_id,
            'field_id' => (string) $field->field_id,
            'parent_field_id' => $field->parent_field_id === null ? null : (string) $field->parent_field_id,
            'path' => (string) $field->path,
            'name' => (string) $field->name,
            'type' => (string) $field->type,
            'cardinality' => (string) $field->cardinality,
            'is_system' => (bool) $field->is_system,
            'position' => (int) $field->position,
            'projection_version' => (int) $field->projection_version,
            'constraints' => $field->constraints === null
                ? null
                : $this->json->decodeMap($field->constraints, 'dp_schema_fields.constraints'),
            'metadata' => $field->metadata === null
                ? null
                : $this->json->decodeMap($field->metadata, 'dp_schema_fields.metadata'),
            'created_at' => (string) $field->created_at,
            'updated_at' => (string) $field->updated_at,
        ];
    }

    private function definitionQuery(): Builder
    {
        return DB::table('dp_record_definitions as definition')
            ->select('definition.*')
            ->selectSub(
                DB::table('dp_schema_fields as field')
                    ->selectRaw('count(*)')
                    ->whereColumn('field.schema_version_id', 'definition.current_schema_version_id'),
                'fields_count',
            )
            ->selectSub(
                DB::table('dp_records as record')
                    ->selectRaw('count(*)')
                    ->whereColumn('record.record_definition_id', 'definition.id')
                    ->whereNull('record.deleted_at'),
                'records_count',
            );
    }
}
