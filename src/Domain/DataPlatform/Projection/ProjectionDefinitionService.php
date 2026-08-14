<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldType;
use Polymorph\Platform\Domain\DataPlatform\Query\JsonPathExpression;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Owns versioned projection intent and background expression-index evolution. */
final class ProjectionDefinitionService
{
    public function __construct(
        private readonly SchemaCatalog $schemas,
        private readonly DatabaseJson $json,
        private readonly JsonPathExpression $expressions,
    ) {}

    public function synchronize(string $schemaVersionId): void
    {
        $previousVersionId = DB::table('dp_schema_versions')
            ->where('id', $schemaVersionId)
            ->value('previous_version_id');
        $previousVersionId = is_string($previousVersionId) && $previousVersionId !== ''
            ? $previousVersionId
            : null;
        $fields = $this->schemas->fields($schemaVersionId);
        $expected = [];
        foreach ($fields as $field) {
            $metadata = $field->metadata;
            $kinds = match ($field->type) {
                FieldType::REF => ['ref_edge'],
                FieldType::MEDIA => ['media_edge'],
                default => [],
            };
            if (($metadata['unique'] ?? false) === true) {
                $kinds[] = 'unique';
            }
            if (($metadata['search'] ?? false) === true) {
                $kinds[] = 'search';
            }
            if (($metadata['display'] ?? false) === true) {
                $kinds[] = 'display';
            }
            if (($metadata['indexed'] ?? false) === true
                && ! $field->multiValued
                && ! in_array($field->type, [FieldType::REF, FieldType::MEDIA, FieldType::JSON], true)) {
                $kinds[] = 'expression_index';
            }
            foreach (array_unique($kinds) as $kind) {
                $expected[] = [$field->id, $kind];
                $global = in_array($kind, ['ref_edge', 'media_edge', 'unique', 'search', 'display'], true);
                $now = now();
                $config = ['path' => $field->path, 'type' => $field->typeName()];
                $state = $global
                    ? $this->synchronousProjectionState($previousVersionId, $field->id, $kind, $field->projectionVersion, $config)
                    : ProjectionState::Pending;
                DB::table('dp_projection_definitions')->updateOrInsert([
                    'schema_version_id' => $schemaVersionId,
                    'field_id' => $field->id,
                    'kind' => $kind,
                ], fn (bool $exists): array => [
                    'version' => $field->projectionVersion,
                    'state' => $state->value,
                    'config' => $this->json->encode($config),
                    'last_error' => null,
                    'applied_at' => $state === ProjectionState::Applied ? $now : null,
                    ...($exists ? [] : ['created_at' => $now]),
                    'updated_at' => $now,
                ]);
            }
        }
        $stale = DB::table('dp_projection_definitions')->where('schema_version_id', $schemaVersionId)->get(['id', 'field_id', 'kind']);
        foreach ($stale as $row) {
            if (! in_array([(string) $row->field_id, (string) $row->kind], $expected, true)) {
                DB::table('dp_projection_definitions')->where('id', $row->id)->delete();
            }
        }
    }

    /** Marks projections populated synchronously by a completed record migration. */
    public function markSynchronousRebuilt(string $schemaVersionId): void
    {
        DB::table('dp_projection_definitions')
            ->where('schema_version_id', $schemaVersionId)
            ->whereIn('kind', ['ref_edge', 'media_edge', 'unique', 'search', 'display'])
            ->update([
                'state' => ProjectionState::Applied->value,
                'last_error' => null,
                'applied_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /** @return array{processed:int,applied:int,failed:int} */
    public function applyPending(?string $schemaVersionId = null, int $limit = 20, bool $dryRun = false): array
    {
        $query = DB::table('dp_projection_definitions as projection')
            ->join('dp_schema_versions as version', 'version.id', '=', 'projection.schema_version_id')
            ->where('projection.kind', 'expression_index')
            ->whereIn('projection.state', [ProjectionState::Pending->value, ProjectionState::Failed->value])
            ->orderBy('projection.id')->limit(max(1, $limit));
        if ($schemaVersionId !== null) {
            $query->where('projection.schema_version_id', $schemaVersionId);
        }
        $rows = $query->get(['projection.*', 'version.record_definition_id']);
        $processed = $applied = $failed = 0;
        foreach ($rows as $row) {
            $processed++;
            if ($dryRun) {
                continue;
            }
            DB::table('dp_projection_definitions')->where('id', $row->id)->update([
                'state' => ProjectionState::Applying->value,
                'updated_at' => now(),
            ]);
            try {
                $config = $this->json->decodeMap($row->config, 'dp_projection_definitions.config');
                $expression = $this->expressions->text((string) $config['path'], 'data');
                $cast = $this->expressions->cast((string) $config['type']) ?? 'text';
                $index = 'dp_records_expr_'.substr(hash('sha256', (string) $row->id), 0, 20).'_idx';
                DB::statement(sprintf(
                    'CREATE INDEX IF NOT EXISTS %s ON dp_records (((%s)::%s)) WHERE record_definition_id = %d AND deleted_at IS NULL',
                    $index,
                    $expression,
                    $cast,
                    (int) $row->record_definition_id,
                ));
                DB::table('dp_projection_definitions')->where('id', $row->id)->update([
                    'state' => ProjectionState::Applied->value,
                    'last_error' => null,
                    'applied_at' => now(),
                    'updated_at' => now(),
                ]);
                $applied++;
            } catch (\Throwable $exception) {
                DB::table('dp_projection_definitions')->where('id', $row->id)->update([
                    'state' => ProjectionState::Failed->value,
                    'last_error' => mb_substr(
                        $exception::class.': '.$exception->getMessage(),
                        0,
                        max(1, (int) config('data_platform.projection.max_error_length')),
                    ),
                    'updated_at' => now(),
                ]);
                $failed++;
            }
        }

        return compact('processed', 'applied', 'failed');
    }

    /** @param array{path:string,type:string} $config */
    private function synchronousProjectionState(
        ?string $previousVersionId,
        string $fieldId,
        string $kind,
        int $version,
        array $config,
    ): ProjectionState {
        // The first version cannot have pre-existing records. A successor may
        // inherit an already complete global projection only when its identity,
        // version, and source configuration are unchanged.
        if ($previousVersionId === null) {
            return ProjectionState::Applied;
        }
        $previous = DB::table('dp_projection_definitions')
            ->where('schema_version_id', $previousVersionId)
            ->where('field_id', $fieldId)
            ->where('kind', $kind)
            ->first(['version', 'state', 'config']);
        if ($previous === null
            || (string) $previous->state !== ProjectionState::Applied->value
            || (int) $previous->version !== $version) {
            return ProjectionState::Pending;
        }

        return $this->json->decodeMap($previous->config, 'dp_projection_definitions.config') === $config
            ? ProjectionState::Applied
            : ProjectionState::Pending;
    }
}
