<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Query;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Records\Core\Query\RecordQueryCondition;
use Polymorph\Platform\Domain\Records\Core\Query\RecordQueryCriteria;
use Polymorph\Platform\Domain\Records\Core\Query\RecordQueryStrategy;
use Polymorph\Platform\Domain\Records\Support\RecordSchemaScalarValueNormalizer;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Сборка RecordQueryCriteria, нормализация query values и strict/observability policy.
 */
final class RecordQueryCriteriaFactory
{
    /** @var array<string, true> */
    private array $loggedWarnings = [];

    public function __construct(
        private readonly RecordSchemaScalarValueNormalizer $scalarNormalizer,
        private readonly AppLogger $logger,
    ) {}

    /**
     * Сборка критериев из «сырых» частей запроса. Единый источник нормализации
     * значений и индексных проверок: V2 `Polymorph\Sdk\Data\Query` (через DataPlatform)
     * делегирует сюда.
     *
     * @param list<array{field: string, op: string, value: mixed}> $conditions
     * @param list<array{field: string, dir: string}> $orders
     */
    public function fromParts(
        RecordDefinition $definition,
        array $conditions,
        ?int $authorId,
        array $orders,
        ?int $limit,
    ): RecordQueryCriteria {
        $this->loggedWarnings = [];
        $metadata = $this->loadFieldMetadata($definition);

        $builtConditions = [];
        foreach ($conditions as $condition) {
            $field = (string) $condition['field'];
            $op = (string) $condition['op'];
            $meta = $this->resolveFieldMeta($field, $metadata);
            $this->enforceIndexedFieldUsage($definition->id, $field, $op, 'filter', $meta);

            $builtConditions[] = new RecordQueryCondition(
                key: $field,
                type: $meta->type->value,
                cast: $meta->cast,
                op: $op,
                value: $this->normalizeQueryValue($meta, $op, $condition['value']),
                strategy: $this->resolveStrategy($op, $meta),
            );
        }

        $builtOrders = [];
        foreach ($orders as $order) {
            $field = (string) $order['field'];
            $meta = $this->resolveFieldMeta($field, $metadata);
            $this->enforceIndexedFieldUsage($definition->id, $field, 'sort', 'sort', $meta);

            $builtOrders[] = [
                'key' => $field,
                'cast' => $meta->cast,
                'dir' => $order['dir'] === 'desc' ? 'desc' : 'asc',
            ];
        }

        return new RecordQueryCriteria(
            definitionId: (int) $definition->id,
            conditions: $builtConditions,
            authorId: $authorId,
            orders: $builtOrders,
            limit: $limit,
        );
    }

    /**
     * @return array<string, RecordQueryFieldMeta>
     */
    public function fieldMetadata(RecordDefinition $definition): array
    {
        return $this->loadFieldMetadata($definition);
    }

    /**
     * @param  array<string, RecordQueryFieldMeta>  $metadata
     */
    public function resolveFieldMeta(string $field, array $metadata): RecordQueryFieldMeta
    {
        if (! isset($metadata[$field])) {
            throw new \InvalidArgumentException("Unknown filterable field '{$field}'.");
        }

        return $metadata[$field];
    }

    /**
     * @return array<string, RecordQueryFieldMeta>
     */
    private function loadFieldMetadata(RecordDefinition $definition): array
    {
        $metadata = [];
        $fields = Field::query()
            ->where('schema_id', (int) $definition->schema_id)
            ->get(['full_path', 'type', 'is_indexed', 'metadata']);

        foreach ($fields as $field) {
            $name = (string) $field->full_path;
            if ($field->type->isContainer()) {
                continue;
            }

            $fieldMetadata = is_array($field->metadata) ? $field->metadata : [];

            $metadata[$name] = new RecordQueryFieldMeta(
                fullPath: $name,
                type: $field->type,
                cast: $field->type->sqlCast(),
                isIndexed: (bool) $field->is_indexed,
                isUnique: (bool) ($fieldMetadata['unique'] ?? false),
            );
        }

        return $metadata;
    }

    private function enforceIndexedFieldUsage(
        int $definitionId,
        string $field,
        string $op,
        string $usage,
        RecordQueryFieldMeta $meta,
    ): void {
        $allowed = $usage === 'sort'
            ? $meta->allowsSort()
            : $meta->allowsFilter($op);

        if ($allowed) {
            return;
        }

        $message = "Field '{$field}' is not indexed on definition {$definitionId} ({$usage}).";

        if (config('records.query.warn_unindexed', true)) {
            $logKey = "{$definitionId}:{$field}:{$usage}:{$op}";
            if (! isset($this->loggedWarnings[$logKey])) {
                $this->loggedWarnings[$logKey] = true;
                $this->logger->warning($message, [
                    'event' => 'records.query.unindexed_field',
                    'definition_id' => $definitionId,
                    'field' => $field,
                    'usage' => $usage,
                    'op' => $op,
                ]);
            }
        }

        if (config('records.query.strict_indexed', false)) {
            throw new \InvalidArgumentException($message);
        }
    }

    private function normalizeQueryValue(RecordQueryFieldMeta $meta, string $op, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($op === 'in' && is_array($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->normalizeScalarQueryValue($meta, $item),
                $value,
            );
        }

        return $this->normalizeScalarQueryValue($meta, $value);
    }

    private function normalizeScalarQueryValue(RecordQueryFieldMeta $meta, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($meta->type) {
            FieldType::INT, FieldType::REF => is_numeric($value) ? (int) $value : $value,
            FieldType::FLOAT => is_numeric($value) ? (float) $value : $value,
            FieldType::BOOL => is_bool($value)
                ? $value
                : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value,
            FieldType::DATETIME => $this->scalarNormalizer->normalizeDateTime($value),
            default => $value,
        };
    }

    private function resolveStrategy(string $op, RecordQueryFieldMeta $meta): RecordQueryStrategy
    {
        if ($op !== '=') {
            return RecordQueryStrategy::Expression;
        }

        // Есть выделенный partial-индекс под это поле → equality по нему селективнее
        // общего GIN (и согласовано с `in`, которое тоже идёт через expression-индекс).
        if ($meta->hasMatchingExpressionIndex()) {
            return RecordQueryStrategy::Expression;
        }

        // Нет per-field индекса → опираемся на общий GIN-containment (data_json @> ?).
        if ($meta->supportsGinEquality()) {
            return RecordQueryStrategy::Containment;
        }

        return RecordQueryStrategy::Expression;
    }
}
