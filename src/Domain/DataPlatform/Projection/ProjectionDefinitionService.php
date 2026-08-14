<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Control\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Query\JsonPathExpression;
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
        $fields = $this->schemas->fields($schemaVersionId);
        $expected = [];
        foreach ($fields as $field) {
            $metadata = $field->metadata;
            $kinds = match ($field->type) {
                'ref' => ['ref_edge'],
                'media' => ['media_edge'],
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
                && ! in_array($field->type, ['ref', 'media', 'json'], true)) {
                $kinds[] = 'expression_index';
            }
            foreach (array_unique($kinds) as $kind) {
                $expected[] = [$field->id, $kind];
                $global = in_array($kind, ['ref_edge', 'media_edge', 'unique', 'search', 'display'], true);
                $now = now();
                DB::table('dp_projection_definitions')->updateOrInsert([
                    'schema_version_id' => $schemaVersionId,
                    'field_id' => $field->id,
                    'kind' => $kind,
                ], fn (bool $exists): array => [
                    'version' => $field->projectionVersion,
                    'state' => $global ? 'applied' : 'pending',
                    'config' => $this->json->encode([
                        'path' => $field->path,
                        'type' => $field->type,
                    ]),
                    'last_error' => null,
                    'applied_at' => $global ? $now : null,
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

    /** @return array{processed:int,applied:int,failed:int} */
    public function applyPending(?string $schemaVersionId = null, int $limit = 20, bool $dryRun = false): array
    {
        $query = DB::table('dp_projection_definitions as projection')
            ->join('dp_schema_versions as version', 'version.id', '=', 'projection.schema_version_id')
            ->where('projection.kind', 'expression_index')
            ->whereIn('projection.state', ['pending', 'failed'])
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
            DB::table('dp_projection_definitions')->where('id', $row->id)->update(['state' => 'applying', 'updated_at' => now()]);
            if (DB::getDriverName() !== 'pgsql') {
                DB::table('dp_projection_definitions')->where('id', $row->id)->update([
                    'state' => 'failed',
                    'last_error' => 'Expression indexes require the PostgreSQL driver.',
                    'updated_at' => now(),
                ]);
                $failed++;

                continue;
            }
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
                    'state' => 'applied', 'last_error' => null, 'applied_at' => now(), 'updated_at' => now(),
                ]);
                $applied++;
            } catch (\Throwable $exception) {
                DB::table('dp_projection_definitions')->where('id', $row->id)->update([
                    'state' => 'failed',
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
}
