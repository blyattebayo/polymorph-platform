<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Control;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaState;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Creates a definition together with its initial mutable schema snapshot. */
final class DefinitionService
{
    public const CODE_PATTERN = '/^[a-z][a-z0-9_-]{1,127}$/D';

    public function __construct(
        private readonly SchemaDraftService $drafts,
        private readonly DatabaseJson $json,
    ) {}

    /** @param list<FieldSpecification> $fields @param array<string, mixed> $metadata */
    public function create(string $code, string $name, array $fields, array $metadata = []): CreatedDefinition
    {
        $code = $this->definitionCode($code);

        return DB::transaction(function () use ($code, $name, $fields, $metadata): CreatedDefinition {
            $definitionId = (int) DB::table('dp_record_definitions')->insertGetId([
                'code' => $code,
                'name' => trim($name),
                'metadata' => $this->json->encodeNullableMap($metadata),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $versionId = (string) Str::ulid();
            DB::table('dp_schema_versions')->insert([
                'id' => $versionId,
                'record_definition_id' => $definitionId,
                'version' => 1,
                'state' => SchemaState::Draft->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->drafts->replaceFields($versionId, $fields);

            return new CreatedDefinition($definitionId, $versionId);
        });
    }

    private function definitionCode(string $code): string
    {
        $code = trim($code);
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw DataPlatformBadRequest::because(
                'invalid_definition_code',
                'Definition code must be a lowercase slug.',
                ['code' => $code],
            );
        }

        return $code;
    }
}
