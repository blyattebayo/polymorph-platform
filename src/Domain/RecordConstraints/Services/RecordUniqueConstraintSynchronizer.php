<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordConstraints\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\RecordConstraints\Exceptions\RecordUniqueConstraintConflictException;
use Polymorph\Platform\Domain\RecordConstraints\Support\RecordUniqueIndexName;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Records\Query\RecordFieldSqlExpression;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Support\Validation\ValidationConstraints;

/** Owns the transactional PostgreSQL constraints encoded by field metadata.unique. */
final class RecordUniqueConstraintSynchronizer
{
    public function synchronizeSchema(int $schemaId): void
    {
        if (! $this->supported() || $schemaId <= 0) {
            return;
        }

        RecordDefinition::query()
            ->where('schema_id', $schemaId)
            ->get(['id', 'schema_id'])
            ->each(fn (RecordDefinition $definition) => $this->synchronizeDefinition($definition));
    }

    public function synchronizeDefinition(RecordDefinition $definition): void
    {
        if (! $this->supported()) {
            return;
        }

        $definitionId = (int) $definition->id;
        $schemaId = (int) ($definition->schema_id ?? 0);
        if ($definitionId <= 0) {
            return;
        }

        $desired = $schemaId > 0 ? $this->desiredIndexes($definitionId, $schemaId) : [];
        $existing = [];

        foreach ($this->existingIndexes($definitionId) as $name => $valid) {
            if (! $valid) {
                DB::statement("DROP INDEX IF EXISTS {$name}");

                continue;
            }

            $existing[] = $name;
        }

        foreach ($desired as $name => $definition) {
            if (! in_array($name, $existing, true)) {
                // Deliberately non-CONCURRENT: failure (including duplicate data) must roll back the mutation.
                try {
                    DB::statement($definition['sql']);
                } catch (QueryException $exception) {
                    if ($this->sqlState($exception) !== '23505') {
                        throw $exception;
                    }

                    throw new RecordUniqueConstraintConflictException(
                        $definitionId,
                        $definition['field_path'],
                        $exception,
                    );
                }
            }
        }

        foreach ($existing as $name) {
            if (! isset($desired[$name])) {
                DB::statement("DROP INDEX IF EXISTS {$name}");
            }
        }
    }

    public function dropDefinition(int $definitionId): void
    {
        if (! $this->supported() || $definitionId <= 0) {
            return;
        }

        foreach (array_keys($this->existingIndexes($definitionId)) as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }

    /** @return array<string, array{sql:string,field_path:string}> */
    private function desiredIndexes(int $definitionId, int $schemaId): array
    {
        $desired = [];
        $predicate = "WHERE record_definition_id = {$definitionId} AND deleted_at IS NULL";

        $fields = Field::query()
            ->where('schema_id', $schemaId)
            ->get(['full_path', 'type', 'cardinality', 'metadata']);

        foreach ($fields as $field) {
            $metadata = is_array($field->metadata) ? $field->metadata : [];
            $fieldPath = (string) $field->full_path;
            if (! (bool) ($metadata['unique'] ?? false)) {
                continue;
            }

            if (! ValidationConstraints::slug()->matches($fieldPath)
                || $field->type->isContainer()
                || $field->cardinality->value === 'many') {
                throw new \LogicException("Persisted field '{$fieldPath}' has unsupported unique configuration");
            }

            $cast = $field->type->sqlCast();
            $name = RecordUniqueIndexName::forField($definitionId, $fieldPath, $cast);
            $expression = RecordFieldSqlExpression::indexExpression($fieldPath, $cast);
            $desired[$name] = [
                'sql' => "CREATE UNIQUE INDEX {$name} ON records {$expression} {$predicate}",
                'field_path' => $fieldPath,
            ];
        }

        return $desired;
    }

    /** @return array<string, bool> */
    private function existingIndexes(int $definitionId): array
    {
        $rows = DB::select(
            'SELECT c.relname AS name, i.indisvalid AS valid '
            .'FROM pg_class c '
            .'JOIN pg_index i ON i.indexrelid = c.oid '
            .'JOIN pg_class t ON t.oid = i.indrelid '
            ."WHERE t.relname = 'records' AND c.relname LIKE ?",
            ["uq_recf_{$definitionId}\_%"],
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->name] = (bool) $row->valid;
        }

        return $result;
    }

    private function supported(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    private function sqlState(QueryException $exception): string
    {
        $previous = $exception->getPrevious();

        return (string) ($previous?->getCode() ?: $exception->getCode());
    }
}
