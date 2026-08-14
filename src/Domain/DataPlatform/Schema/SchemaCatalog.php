<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Schema;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformStateConflict;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;

final class SchemaCatalog
{
    public function __construct(private readonly SchemaFieldMapper $fields) {}

    /** @return array<string, mixed>|null */
    public function findDefinitionByCode(string $code): ?array
    {
        $row = DB::table('dp_record_definitions')->where('code', $code)->first();

        return $row === null ? null : (array) $row;
    }

    public function hasUnfinishedSchemaWork(int $definitionId): bool
    {
        return DB::table('dp_schema_versions')
            ->where('record_definition_id', $definitionId)
            ->whereIn('state', [SchemaState::Validating->value, SchemaState::Backfilling->value])
            ->exists();
    }

    public function latestDraftVersionId(int $definitionId): ?string
    {
        $id = DB::table('dp_schema_versions')
            ->where('record_definition_id', $definitionId)
            ->where('state', SchemaState::Draft->value)
            ->orderByDesc('version')
            ->value('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @return array{definition:array<string,mixed>,version:array<string,mixed>,fields:list<FieldDefinition>}
     */
    public function writableDefinition(int $definitionId, ?string $schemaVersionId = null): array
    {
        return $this->definitionVersion($definitionId, $schemaVersionId, [SchemaState::Published]);
    }

    /**
     * @param  list<SchemaState>  $allowedStates
     * @return array{definition:array<string,mixed>,version:array<string,mixed>,fields:list<FieldDefinition>}
     */
    public function definitionVersion(int $definitionId, ?string $schemaVersionId, array $allowedStates): array
    {
        $definition = DB::table('dp_record_definitions')->where('id', $definitionId)->first();
        if ($definition === null) {
            throw DataPlatformResourceNotFound::for('record-definition', $definitionId);
        }

        $versionId = $schemaVersionId ?? $definition->current_schema_version_id;
        if (! is_string($versionId) || $versionId === '') {
            throw DataPlatformStateConflict::because(
                'definition_has_no_published_schema',
                "Record definition {$definitionId} has no published schema.",
                ['record_definition_id' => $definitionId],
            );
        }

        $version = DB::table('dp_schema_versions')
            ->where('id', $versionId)
            ->where('record_definition_id', $definitionId)
            ->first();
        $allowed = array_map(static fn (SchemaState $state): string => $state->value, $allowedStates);
        if ($version === null || ! in_array((string) $version->state, $allowed, true)) {
            throw DataPlatformStateConflict::because(
                'schema_version_state_not_allowed',
                "Schema version {$versionId} is not in an allowed state for this operation.",
                ['schema_version_id' => $versionId, 'allowed_states' => $allowed],
            );
        }

        return [
            'definition' => (array) $definition,
            'version' => (array) $version,
            'fields' => $this->fields($versionId),
        ];
    }

    /** @return list<FieldDefinition> */
    public function fields(string $schemaVersionId): array
    {
        $rows = SchemaStorage::orderedFields(
            DB::table('dp_schema_fields')->where('schema_version_id', $schemaVersionId),
        )->get();

        return $this->fields->fromRows($rows);
    }

    /** @return array<string, FieldDefinition> */
    public function fieldsByPath(string $schemaVersionId): array
    {
        $result = [];
        foreach ($this->fields($schemaVersionId) as $field) {
            $result[$field->path] = $field;
        }

        return $result;
    }
}
