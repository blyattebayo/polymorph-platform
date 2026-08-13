<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordIndexes\Services;

use Illuminate\Support\Facades\DB;
use LogicException;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\RecordIndexes\Support\RecordIndexName;
use Polymorph\Platform\Domain\Records\Query\RecordFieldSqlExpression;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\SharedKernel\SystemFields\SystemFieldNames;
use Polymorph\Platform\Support\Validation\ValidationConstraints;

/**
 * Reconciles derivative query-performance indexes. Domain uniqueness is deliberately
 * excluded and is owned by RecordUniqueConstraintSynchronizer.
 */
final class RecordIndexReconciler
{
    /** Namespace-тег для session-level advisory-локов реконсайла (избегаем коллизий с другими локами). */
    private const ADVISORY_LOCK_NAMESPACE = 0x52494458; // 'RIDX'

    /** Синхронизировать индексы всех определений схемы (поля схемо-скоупны). */
    public function reconcileSchema(int $schemaId): void
    {
        if (! $this->supported()) {
            return;
        }

        $definitionIds = RecordDefinition::query()
            ->where('schema_id', $schemaId)
            ->pluck('id');

        foreach ($definitionIds as $definitionId) {
            $this->reconcileDefinition((int) $definitionId);
        }
    }

    /** Синхронизировать индексы одного определения. */
    public function reconcileDefinition(int $definitionId): void
    {
        if (! $this->supported()) {
            return;
        }

        // Session-level lock is required because CONCURRENTLY cannot run in a transaction.
        $this->withDefinitionLock($definitionId, function () use ($definitionId): void {
            $schemaId = (int) RecordDefinition::query()->whereKey($definitionId)->value('schema_id');
            $this->reconcile($definitionId, $schemaId > 0 ? $schemaId : null);
        });
    }

    /**
     * Невалидные индексы records по нашим префиксам (например, недостроенные упавшим
     * CREATE … CONCURRENTLY) во всей таблице. Имя → record_definition_id.
     *
     * @return array<string, int>
     */
    public function invalidIndexes(): array
    {
        if (! $this->supported()) {
            return [];
        }

        $rows = DB::select(
            'SELECT c.relname AS name '
            .'FROM pg_class c '
            .'JOIN pg_index i ON i.indexrelid = c.oid '
            .'JOIN pg_class t ON t.oid = i.indrelid '
            ."WHERE t.relname = 'records' AND i.indisvalid = false "
            ."AND (c.relname LIKE 'idx_reca\_%' OR c.relname LIKE 'idx_recf\_%')",
        );

        $result = [];
        foreach ($rows as $row) {
            $name = (string) $row->name;
            if (preg_match('/^(?:idx_reca|idx_recf)_(\d+)/', $name, $m) === 1) {
                $result[$name] = (int) $m[1];
            }
        }

        return $result;
    }

    private function reconcile(int $definitionId, ?int $schemaId): void
    {
        $desired = $schemaId === null ? [] : $this->desiredIndexes($definitionId, $schemaId);

        // Невалидные индексы (например, прерванный CREATE … CONCURRENTLY) видны
        // в каталоге и занимают имя — IF NOT EXISTS их не пересоздаст. Сначала дропаем,
        // затем создаём заново; иначе stale-invalid индекс висит вечно.
        $existing = [];
        foreach ($this->existingIndexes($definitionId) as $name => $valid) {
            if (! $valid) {
                DB::statement($this->dropIndexSql($name));

                continue;
            }
            $existing[] = $name;
        }

        foreach ($desired as $name => $sql) {
            if (! in_array($name, $existing, true)) {
                DB::statement($sql);
            }
        }

        foreach ($existing as $name) {
            if (! isset($desired[$name])) {
                DB::statement($this->dropIndexSql($name));
            }
        }
    }

    /**
     * Желаемый набор индексов: имя → SQL создания.
     *
     * @return array<string, string>
     */
    private function desiredIndexes(int $definitionId, int $schemaId): array
    {
        $predicate = "WHERE record_definition_id = {$definitionId} AND deleted_at IS NULL";

        // Скоуп владельца идёт через author_id (реальная колонка).
        $authorName = "idx_reca_{$definitionId}";
        $desired = [
            $authorName => "CREATE INDEX CONCURRENTLY IF NOT EXISTS {$authorName} ON records (author_id) {$predicate}",
        ];

        $fields = Field::query()
            ->where('schema_id', $schemaId)
            ->get(['full_path', 'type', 'cardinality', 'is_indexed', 'is_system']);

        foreach ($fields as $field) {
            if (! $field->is_indexed) {
                continue;
            }

            $name = (string) $field->full_path;
            $supportedPath = ValidationConstraints::slug()->matches($name)
                || ((bool) $field->is_system && in_array($name, SystemFieldNames::writableKeys(), true));
            if (! $supportedPath
                || $field->type->isContainer()
                || $field->cardinality->value === 'many') {
                throw new LogicException("Persisted field '{$name}' has unsupported is_indexed configuration");
            }

            $cast = $field->type->sqlCast();

            $expr = RecordFieldSqlExpression::indexExpression($name, $cast);
            $indexName = RecordIndexName::fieldIndex($definitionId, $name, $cast);
            $desired[$indexName] = "CREATE INDEX CONCURRENTLY IF NOT EXISTS {$indexName} ON records {$expr} {$predicate}";
        }

        return $desired;
    }

    /**
     * Индексы records, принадлежащие данному определению (по нашим префиксам), с флагом валидности.
     *
     * @return array<string, bool> имя индекса → indisvalid
     */
    private function existingIndexes(int $definitionId): array
    {
        $rows = DB::select(
            'SELECT c.relname AS name, i.indisvalid AS valid '
            .'FROM pg_class c '
            .'JOIN pg_index i ON i.indexrelid = c.oid '
            .'JOIN pg_class t ON t.oid = i.indrelid '
            ."WHERE t.relname = 'records' "
            .'AND (c.relname = ? OR c.relname LIKE ?)',
            ["idx_reca_{$definitionId}", "idx_recf_{$definitionId}\_%"],
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->name] = (bool) $row->valid;
        }

        return $result;
    }

    private function dropIndexSql(string $name): string
    {
        return "DROP INDEX CONCURRENTLY IF EXISTS {$name}";
    }

    /**
     * @param  callable():void  $callback
     */
    private function withDefinitionLock(int $definitionId, callable $callback): void
    {
        DB::select('SELECT pg_advisory_lock(?, ?)', [self::ADVISORY_LOCK_NAMESPACE, $definitionId]);

        try {
            $callback();
        } finally {
            DB::select('SELECT pg_advisory_unlock(?, ?)', [self::ADVISORY_LOCK_NAMESPACE, $definitionId]);
        }
    }

    private function supported(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }
}
