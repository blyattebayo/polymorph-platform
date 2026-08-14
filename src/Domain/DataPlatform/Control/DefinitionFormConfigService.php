<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformStateConflict;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaStorage;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Stores form configuration against the exact editable schema version. */
final class DefinitionFormConfigService
{
    public function __construct(
        private readonly DatabaseJson $json,
        private readonly SchemaCatalog $schemas,
    ) {}

    /** @return array<string, mixed> */
    public function get(int $definitionId): array
    {
        $definition = DB::table('dp_record_definitions')->where('id', $definitionId)->first();
        if ($definition === null) {
            throw DataPlatformResourceNotFound::for('record-definition', $definitionId);
        }
        $metadata = $this->json->decodeMap($definition->metadata, SchemaStorage::DEFINITION_METADATA_CONTEXT);
        $versionId = $this->editableVersionId($definitionId, $definition);
        $config = $metadata['form_configs'][$versionId] ?? [];

        return is_array($config) ? $config : [];
    }

    /** @param array<string, mixed> $config @return array<string, mixed> */
    public function update(int $definitionId, array $config): array
    {
        return DB::transaction(function () use ($definitionId, $config): array {
            $definition = DB::table('dp_record_definitions')->where('id', $definitionId)->lockForUpdate()->first();
            if ($definition === null) {
                throw DataPlatformResourceNotFound::for('record-definition', $definitionId);
            }
            $metadata = $this->json->decodeMap($definition->metadata, SchemaStorage::DEFINITION_METADATA_CONTEXT);
            $versionId = $this->editableVersionId($definitionId, $definition);
            $metadata['form_configs'][$versionId] = $config;
            DB::table('dp_record_definitions')->where('id', $definitionId)->update([
                'metadata' => $this->json->encodeNullableMap($metadata),
                'updated_at' => now(),
            ]);

            return [
                'record_definition_id' => $definitionId,
                'schema_id' => $versionId,
                'config_json' => $config,
                'updated_at' => now()->toAtomString(),
            ];
        });
    }

    private function editableVersionId(int $definitionId, object $definition): string
    {
        $versionId = $this->schemas->latestDraftVersionId($definitionId) ?? $definition->current_schema_version_id;
        if (! is_string($versionId) || $versionId === '') {
            throw DataPlatformStateConflict::because(
                'definition_has_no_schema_version',
                'The definition has no schema version.',
                ['record_definition_id' => $definitionId],
            );
        }

        return $versionId;
    }
}
