<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Services;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Materialization\Support\RecordIndexNameBuilder;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Records\Query\RecordFieldSqlExpression;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Support\Validation\ValidationConstraints;

/**
 * Материализует поля схемы, помеченные is_indexed, в partial expression-индексы
 * Postgres на таблице records (изолированные по record_definition_id), плюс индекс
 * по author_id. Полный реконсайл: создаёт недостающие и дропает устаревшие
 * (поле удалили / is_indexed выключили / сменился тип). Источник правды каста —
 * FieldType::sqlCast().
 *
 * Это ответственность ядра (платформы данных), не плагина: одинаково работает для
 * admin- и plugin-определений; триггерится событиями изменения схемы/определения.
 */
final class RecordIndexMaterializer
{
    /** Namespace-тег для session-level advisory-локов реконсайла (избегаем коллизий с другими локами). */
    private const ADVISORY_LOCK_NAMESPACE = 0x52494458; // 'RIDX'

    /** Синхронизировать индексы всех определений схемы (поля схемо-скоупны). */
    public function syncSchema(int $schemaId, bool $concurrent = true): void
    {
        if (! $this->supported()) {
            return;
        }

        $definitionIds = RecordDefinition::query()
            ->where('schema_id', $schemaId)
            ->pluck('id');

        foreach ($definitionIds as $definitionId) {
            $this->syncDefinition((int) $definitionId, $schemaId, $concurrent);
        }
    }

    /** Синхронизировать индексы одного определения. */
    public function syncDefinition(int $definitionId, ?int $schemaId = null, bool $concurrent = true): void
    {
        if (! $this->supported()) {
            return;
        }

        $schemaId ??= (int) RecordDefinition::query()->whereKey($definitionId)->value('schema_id');
        if ($schemaId <= 0) {
            return;
        }

        // Session-level advisory-лок (не xact: CONCURRENTLY нельзя в транзакции),
        // чтобы параллельные джобы не гоняли CREATE/DROP одного имени вперемешку.
        $this->withDefinitionLock($definitionId, function () use ($definitionId, $schemaId, $concurrent): void {
            $this->reconcile($definitionId, $schemaId, $concurrent);
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
            ."AND (c.relname LIKE 'idx_reca\_%' OR c.relname LIKE 'idx_recf\_%' OR c.relname LIKE 'uq_recf\_%')",
        );

        $result = [];
        foreach ($rows as $row) {
            $name = (string) $row->name;
            if (preg_match('/^(?:idx_reca|idx_recf|uq_recf)_(\d+)/', $name, $m) === 1) {
                $result[$name] = (int) $m[1];
            }
        }

        return $result;
    }

    private function reconcile(int $definitionId, int $schemaId, bool $concurrent): void
    {
        $desired = $this->desiredIndexes($definitionId, $schemaId, $concurrent);

        // F1: невалидные индексы (например, упавший CREATE … CONCURRENTLY на дублях) видны
        // в каталоге и занимают имя — IF NOT EXISTS их не пересоздаст. Сначала дропаем,
        // затем создаём заново; иначе stale-invalid индекс висит вечно (и для unique блокирует запись).
        $existing = [];
        foreach ($this->existingIndexes($definitionId) as $name => $valid) {
            if (! $valid) {
                $this->run($this->dropIndexSql($name, $concurrent));

                continue;
            }
            $existing[] = $name;
        }

        foreach ($desired as $name => $sql) {
            if (! in_array($name, $existing, true)) {
                $this->run($sql);
            }
        }

        foreach ($existing as $name) {
            if (! isset($desired[$name])) {
                $this->run($this->dropIndexSql($name, $concurrent));
            }
        }
    }

    /**
     * Желаемый набор индексов: имя → SQL создания.
     *
     * @return array<string, string>
     */
    private function desiredIndexes(int $definitionId, int $schemaId, bool $concurrent): array
    {
        $predicate = "WHERE record_definition_id = {$definitionId} AND deleted_at IS NULL";
        $createKeyword = $concurrent ? 'CREATE INDEX CONCURRENTLY' : 'CREATE INDEX';
        $createUniqueKeyword = $concurrent ? 'CREATE UNIQUE INDEX CONCURRENTLY' : 'CREATE UNIQUE INDEX';

        // Скоуп владельца идёт через author_id (реальная колонка).
        $authorName = "idx_reca_{$definitionId}";
        $desired = [
            $authorName => "{$createKeyword} IF NOT EXISTS {$authorName} ON records (author_id) {$predicate}",
        ];

        $fields = Field::query()
            ->where('schema_id', $schemaId)
            ->get(['full_path', 'type', 'is_indexed', 'metadata']);

        foreach ($fields as $field) {
            $name = (string) $field->full_path;
            if (! ValidationConstraints::slug()->matches($name)) {
                continue;
            }
            if ($field->type->isContainer()) {
                continue;
            }

            $cast = $field->type->sqlCast();

            if ($this->isUnique($field)) {
                // F9: типизированный unique (bigint/numeric/boolean), а не текстовый —
                // согласован с типизированным выражением запроса и нормализацией значений на write.
                $uniqueName = RecordIndexNameBuilder::uniqueIndex($definitionId, $name, $cast);
                $expr = RecordFieldSqlExpression::indexExpression($name, $cast);
                $desired[$uniqueName] =
                    "{$createUniqueKeyword} IF NOT EXISTS {$uniqueName} ON records {$expr} {$predicate}";

                continue;
            }

            if (! $field->is_indexed) {
                continue;
            }

            $expr = RecordFieldSqlExpression::indexExpression($name, $cast);
            $indexName = RecordIndexNameBuilder::fieldIndex($definitionId, $name, $cast);
            $desired[$indexName] = "{$createKeyword} IF NOT EXISTS {$indexName} ON records {$expr} {$predicate}";
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
            .'AND (c.relname = ? OR c.relname LIKE ? OR c.relname LIKE ?)',
            ["idx_reca_{$definitionId}", "idx_recf_{$definitionId}\_%", "uq_recf_{$definitionId}\_%"],
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->name] = (bool) $row->valid;
        }

        return $result;
    }

    private function isUnique(Field $field): bool
    {
        $metadata = is_array($field->metadata) ? $field->metadata : [];

        return (bool) ($metadata['unique'] ?? false);
    }

    private function dropIndexSql(string $name, bool $concurrent): string
    {
        return $concurrent
            ? "DROP INDEX CONCURRENTLY IF EXISTS {$name}"
            : "DROP INDEX IF EXISTS {$name}";
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

    private function run(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            // Индекс — оптимизация, не корректность: не валим сохранение схемы/определения.
            // Невалидные индексы от упавшего CONCURRENTLY подчистит следующий реконсайл (см. reconcile()).
            report($e);
        }
    }
}
