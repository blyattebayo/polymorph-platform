<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformStateConflict;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeRegistry;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaState;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaStorage;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Creates and edits mutable schema snapshots. */
final class SchemaDraftService
{
    public function __construct(
        private readonly StableFieldRegistry $stableFields,
        private readonly FieldTypeRegistry $types,
        private readonly SchemaValidator $validator,
        private readonly DatabaseJson $json,
        private readonly SchemaCatalog $schemas,
    ) {}

    public function create(int $definitionId): string
    {
        return DB::transaction(function () use ($definitionId): string {
            $definition = DB::table('dp_record_definitions')->where('id', $definitionId)->lockForUpdate()->first();
            if ($definition === null) {
                throw DataPlatformResourceNotFound::for('record-definition', $definitionId);
            }

            $existingDraftId = $this->schemas->latestDraftVersionId($definitionId);
            if ($existingDraftId !== null) {
                return $existingDraftId;
            }

            $previousId = is_string($definition->current_schema_version_id)
                ? $definition->current_schema_version_id
                : null;
            $draftId = (string) Str::ulid();
            DB::table('dp_schema_versions')->insert([
                'id' => $draftId,
                'record_definition_id' => $definitionId,
                'version' => ((int) DB::table('dp_schema_versions')->where('record_definition_id', $definitionId)->max('version')) + 1,
                'state' => SchemaState::Draft->value,
                'previous_version_id' => $previousId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($previousId !== null) {
                $this->copyFields($previousId, $draftId);
                $this->copyFormConfig($definitionId, $definition->metadata, $previousId, $draftId);
            }

            return $draftId;
        });
    }

    /** @param list<FieldSpecification> $specifications */
    public function replaceFields(string $schemaVersionId, array $specifications): void
    {
        DB::transaction(function () use ($schemaVersionId, $specifications): void {
            $version = DB::table('dp_schema_versions')->where('id', $schemaVersionId)->lockForUpdate()->first();
            if ($version === null || $version->state !== SchemaState::Draft->value) {
                throw DataPlatformStateConflict::because(
                    'schema_version_not_draft',
                    'Only a draft schema version can be changed.',
                    ['schema_version_id' => $schemaVersionId],
                );
            }

            $rows = [];
            $fields = [];
            foreach (array_values($specifications) as $specification) {
                $fieldId = $this->stableFields->resolve((int) $version->record_definition_id, $specification);
                $field = $specification->toField($fieldId);
                $this->types->get($field->type)->validateSchema($field);
                $fields[] = $field;
                $rows[] = [
                    'schema_version_id' => $schemaVersionId,
                    'field_id' => $field->id,
                    'parent_field_id' => $field->parentId,
                    'path' => $field->path,
                    'name' => $field->name,
                    'type' => $field->typeName(),
                    'cardinality' => $field->cardinality->value,
                    'is_system' => $field->system,
                    'position' => $specification->position,
                    'projection_version' => $field->projectionVersion,
                    'constraints' => $this->json->encodeNullableMap($field->constraints),
                    'metadata' => $this->json->encodeNullableMap($field->metadata),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            $this->validator->assertUniqueIdentityAndTree($fields);

            DB::table('dp_schema_fields')->where('schema_version_id', $schemaVersionId)->delete();
            if ($rows !== []) {
                DB::table('dp_schema_fields')->insert($rows);
            }
            DB::table('dp_schema_versions')->where('id', $schemaVersionId)->update([
                'checksum' => null,
                'validated_at' => null,
                'updated_at' => now(),
            ]);
        });
    }

    private function copyFields(string $sourceVersionId, string $draftId): void
    {
        $copies = [];
        foreach (SchemaStorage::orderedFields(
            DB::table('dp_schema_fields')->where('schema_version_id', $sourceVersionId),
        )->get() as $row) {
            $copy = (array) $row;
            unset($copy['id']);
            $copy['schema_version_id'] = $draftId;
            $copy['created_at'] = now();
            $copy['updated_at'] = now();
            $copies[] = $copy;
        }
        if ($copies !== []) {
            DB::table('dp_schema_fields')->insert($copies);
        }
    }

    private function copyFormConfig(int $definitionId, mixed $storedMetadata, string $sourceVersionId, string $draftId): void
    {
        $metadata = $this->json->decodeMap($storedMetadata, SchemaStorage::DEFINITION_METADATA_CONTEXT);
        if (! isset($metadata['form_configs'][$sourceVersionId])) {
            return;
        }

        $metadata['form_configs'][$draftId] = $metadata['form_configs'][$sourceVersionId];
        DB::table('dp_record_definitions')->where('id', $definitionId)->update([
            'metadata' => $this->json->encodeNullableMap($metadata),
            'updated_at' => now(),
        ]);
    }
}
