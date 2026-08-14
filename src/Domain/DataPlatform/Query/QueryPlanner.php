<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Polymorph\Platform\Domain\DataPlatform\Access\DataAccessDenied;
use Polymorph\Platform\Domain\DataPlatform\Access\DataAccessPolicy;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformResourceNotFound;
use Polymorph\Platform\Domain\DataPlatform\Fields\Cardinality;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldType;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeHandler;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeRegistry;
use Polymorph\Platform\Domain\DataPlatform\Projection\ProjectionState;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Serialization\CanonicalJson;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

final class QueryPlanner
{
    public function __construct(
        private readonly SchemaCatalog $schemas,
        private readonly FieldTypeRegistry $types,
        private readonly DataAccessPolicy $access,
        private readonly DatabaseJson $json,
        private readonly CanonicalJson $canonicalJson,
        private readonly JsonPathExpression $expressions,
    ) {}

    public function plan(QuerySpec $spec, ?int $actorId): QueryPlan
    {
        if (! $this->access->canReadDefinition($actorId, $spec->recordDefinitionId)) {
            throw DataAccessDenied::for('record-definition.'.$spec->recordDefinitionId, 'read');
        }

        $schema = $this->schemas->writableDefinition($spec->recordDefinitionId);
        $fields = $this->fieldMap($schema['fields']);
        $expressionIndexes = $this->appliedExpressionIndexes($spec->recordDefinitionId, $schema['fields']);
        $uniqueProjections = $this->appliedUniqueProjections($spec->recordDefinitionId, $schema['fields']);
        $query = DB::table('dp_records as r')
            ->where('r.record_definition_id', $spec->recordDefinitionId)
            ->whereNull('r.deleted_at');
        $strategies = [];
        $warnings = [];

        if ($spec->filter !== null) {
            $this->compileNode($query, $spec->filter, $fields, $expressionIndexes, $uniqueProjections, $actorId, $spec, $strategies, $warnings);
        }

        foreach ($spec->sort as $sort) {
            $field = $this->field($fields, $sort['field']);
            $this->assertReadable($actorId, $spec->recordDefinitionId, $field);
            if (! $this->hasAppliedExpressionIndex($field, $expressionIndexes) && ! $spec->allowScan) {
                $this->rejectOrWarn("Sorting by unindexed field '{$field->path}' is disabled.", $warnings);
            }
            $query->orderByRaw($this->sortExpression($field).' '.$sort['direction']);
        }
        foreach (array_values(array_unique($spec->groupBy)) as $identifier) {
            $field = $this->field($fields, $identifier);
            $this->assertReadable($actorId, $spec->recordDefinitionId, $field);
            $this->assertGroupable($field);
            if (! $this->hasAppliedExpressionIndex($field, $expressionIndexes) && ! $spec->allowScan) {
                $this->rejectOrWarn("Grouping by unindexed field '{$field->path}' is disabled.", $warnings);
            }
        }
        // Keep pagination deterministic without surprising callers: ties follow
        // the direction of the last explicit sort, just like the public SDK
        // contract. With no explicit sort, insertion order is stable.
        $tieDirection = $spec->sort === []
            ? 'asc'
            : $spec->sort[array_key_last($spec->sort)]['direction'];
        $query->orderBy('r.id', $tieDirection);

        // Definition and field grants do not imply row visibility. Resolve the
        // candidate IDs without selecting documents, then apply the canonical
        // ACL engine in one batched ruleset evaluation.
        if (! $this->access->applyReadableRecordScope($query, $actorId, $spec->recordDefinitionId)) {
            $candidateCount = (int) (clone $query)->reorder()->count('r.id');
            $maximum = max(1, (int) config('data_platform.query.max_acl_fallback_candidates'));
            if ($candidateCount > $maximum) {
                throw new UnindexedQueryRejected(
                    "Record ACL fallback would inspect {$candidateCount} candidates; maximum is {$maximum}. Configure a SQL-readable scope.",
                );
            }
            $candidateIds = (clone $query)->reorder()->pluck('r.id')->map('intval')->all();
            $readableIds = $this->access->readableTargetRecordIds($actorId, $candidateIds);
            if ($readableIds === []) {
                $query->whereRaw('false');
            } elseif (count($readableIds) !== count($candidateIds)) {
                $query->whereIn('r.id', $readableIds);
            }
        }

        if ($warnings !== []) {
            Log::warning('data_platform.query.scan', [
                'record_definition_id' => $spec->recordDefinitionId,
                'warnings' => $warnings,
            ]);
        }

        return new QueryPlan($query, array_values(array_unique($strategies)), $warnings, $fields);
    }

    /** @return int|float|null|list<array{key:array<string,mixed>,value:int|float|null}> */
    public function aggregate(
        QuerySpec $spec,
        ?int $actorId,
        string $function,
        ?string $fieldIdentifier = null,
        ?QueryPlan $planned = null,
    ): int|float|null|array {
        $function = strtolower($function);
        if (! in_array($function, ['count', 'sum', 'avg', 'min', 'max'], true)) {
            throw DataPlatformBadRequest::because(
                'unsupported_aggregate',
                "Unsupported aggregate '{$function}'.",
                ['aggregate' => $function],
            );
        }
        $plan = $planned ?? $this->plan($spec, $actorId);
        $query = (clone $plan->builder)->reorder();
        if ($spec->groupBy !== []) {
            return $this->groupedAggregate($query, $spec, $actorId, $function, $fieldIdentifier, $plan->fields);
        }
        if ($function === 'count') {
            return (int) $query->count('r.id');
        }
        if (! is_string($fieldIdentifier) || $fieldIdentifier === '') {
            throw DataPlatformBadRequest::because(
                'aggregate_requires_field',
                "Aggregate '{$function}' requires a field.",
                ['aggregate' => $function],
            );
        }
        $field = $this->field($plan->fields, $fieldIdentifier);
        $this->assertReadable($actorId, $spec->recordDefinitionId, $field);
        $this->assertNumericAggregateField($field);
        $cast = $field->type === FieldType::INT ? 'bigint' : 'double precision';
        if ($field->multiValued) {
            $source = (clone $query)->select(['r.id', 'r.data']);
            $value = DB::query()->fromSub($source, 'aggregate_source')
                ->crossJoin(DB::raw('LATERAL jsonb_path_query(aggregate_source.data, '.$this->expressions->jsonPath($field).'::jsonpath) AS dp_occurrence(value)'))
                ->whereRaw("dp_occurrence.value <> 'null'::jsonb")
                ->selectRaw(strtoupper($function)."((dp_occurrence.value #>> '{}')::{$cast}) AS aggregate_value")
                ->value('aggregate_value');

            return $value === null ? null : (float) $value;
        }
        $source = (clone $query)->selectRaw($this->expressions->text($field).' AS table_value');
        $value = DB::query()->fromSub($source, 'aggregate_source')
            ->selectRaw(strtoupper($function).'((table_value)::'.$cast.') AS aggregate_value')
            ->value('aggregate_value');

        return $value === null ? null : (float) $value;
    }

    /** @return list<array{key:array<string,mixed>,value:int|float|null}> */
    private function groupedAggregate(
        Builder $query,
        QuerySpec $spec,
        ?int $actorId,
        string $function,
        ?string $fieldIdentifier,
        array $fields,
    ): array {
        $groupFields = [];
        foreach (array_values(array_unique($spec->groupBy)) as $identifier) {
            $field = $this->field($fields, $identifier);
            $groupFields[] = $field;
        }
        $aggregateExpression = 'COUNT(r.id)';
        if ($function !== 'count') {
            if (! is_string($fieldIdentifier) || $fieldIdentifier === '') {
                throw DataPlatformBadRequest::because(
                    'aggregate_requires_field',
                    "Aggregate '{$function}' requires a field.",
                    ['aggregate' => $function],
                );
            }
            $aggregateField = $this->field($fields, $fieldIdentifier);
            $this->assertReadable($actorId, $spec->recordDefinitionId, $aggregateField);
            $this->assertNumericAggregateField($aggregateField);
            $cast = $aggregateField->type === FieldType::INT ? 'bigint' : 'double precision';
            if ($aggregateField->multiValued) {
                $query->crossJoin(DB::raw('LATERAL jsonb_path_query(r.data, '.$this->expressions->jsonPath($aggregateField).'::jsonpath) AS dp_aggregate_occurrence(value)'))
                    ->whereRaw("dp_aggregate_occurrence.value <> 'null'::jsonb");
                $aggregateExpression = strtoupper($function)."((dp_aggregate_occurrence.value #>> '{}')::{$cast})";
            } else {
                $aggregateExpression = strtoupper($function).'(('.$this->expressions->text($aggregateField).')::'.$cast.')';
            }
        }
        $selects = [];
        $groups = [];
        foreach ($groupFields as $index => $field) {
            $expression = $field->multiValued
                ? 'jsonb_path_query_array(r.data, '.$this->expressions->jsonPath($field).'::jsonpath)::text'
                : $this->expressions->text($field);
            $selects[] = $expression.' AS group_'.$index;
            $groups[] = $expression;
        }
        /** @var Collection<int,\stdClass> $rows */
        $rows = $query->selectRaw(implode(', ', [...$selects, $aggregateExpression.' AS aggregate_value']))
            ->groupByRaw(implode(', ', $groups))
            ->orderByRaw(implode(', ', $groups))
            ->get();

        return $rows->map(function (\stdClass $row) use ($groupFields, $function): array {
            $key = [];
            foreach ($groupFields as $index => $field) {
                $raw = $row->{'group_'.$index};
                $key[$field->id] = $field->multiValued && is_string($raw)
                    ? $this->json->decodeList($raw, 'grouped aggregate JSON value')
                    : $raw;
            }
            $value = $row->aggregate_value;

            return ['key' => $key, 'value' => $value === null ? null : ($function === 'count' ? (int) $value : (float) $value)];
        })->all();
    }

    /**
     * @param  array<string,FieldDefinition>  $fields
     * @param  list<string>  $strategies
     * @param  list<string>  $warnings
     */
    private function compileNode(
        Builder $query,
        FilterNode $node,
        array $fields,
        array $expressionIndexes,
        array $uniqueProjections,
        ?int $actorId,
        QuerySpec $spec,
        array &$strategies,
        array &$warnings,
        string $boolean = 'and',
    ): void {
        if ($node instanceof BooleanNode) {
            $method = $boolean === 'or' ? 'orWhere' : 'where';
            $query->{$method}(function (Builder $nested) use ($node, $fields, $expressionIndexes, $uniqueProjections, $actorId, $spec, &$strategies, &$warnings): void {
                if ($node->operator === 'not') {
                    $nested->whereNot(function (Builder $not) use ($node, $fields, $expressionIndexes, $uniqueProjections, $actorId, $spec, &$strategies, &$warnings): void {
                        $this->compileNode($not, $node->children[0], $fields, $expressionIndexes, $uniqueProjections, $actorId, $spec, $strategies, $warnings);
                    });

                    return;
                }
                foreach ($node->children as $index => $child) {
                    $this->compileNode(
                        $nested,
                        $child,
                        $fields,
                        $expressionIndexes,
                        $uniqueProjections,
                        $actorId,
                        $spec,
                        $strategies,
                        $warnings,
                        $node->operator === 'or' && $index > 0 ? 'or' : 'and',
                    );
                }
            });

            return;
        }
        if (! $node instanceof PredicateNode) {
            throw DataPlatformInvariantViolation::because('unknown_query_filter_node', 'Unknown query filter node.');
        }

        if ($node->field === '$author_id') {
            if ($node->operator !== 'eq' || ! is_int($node->value)) {
                throw DataPlatformBadRequest::because(
                    'invalid_author_predicate',
                    'The $author_id system predicate supports integer eq only.',
                );
            }
            $method = $boolean === 'or' ? 'orWhere' : 'where';
            $query->{$method}('r.author_id', $node->value);
            $strategies[] = 'system_column';

            return;
        }

        if ($node->field === '$search') {
            $this->assertSearchable($actorId, $spec->recordDefinitionId, $fields);
            $this->applySearchPredicate($query, $node, $boolean);
            $strategies[] = 'search_gin';

            return;
        }
        if (str_starts_with($node->field, '$reverse.')) {
            $fieldId = substr($node->field, strlen('$reverse.'));
            $sourceDefinitionId = $this->assertReadableReverseField($actorId, $fieldId);
            $readableSourceIds = $this->readableReverseSourceIds($actorId, $sourceDefinitionId, $fieldId);
            $this->applyReversePredicate(
                $query,
                $fieldId,
                $sourceDefinitionId,
                $readableSourceIds,
                $node,
                $boolean,
            );
            $strategies[] = 'reverse_ref_edge';

            return;
        }

        $field = $this->field($fields, $node->field);
        $this->assertReadable($actorId, $spec->recordDefinitionId, $field);
        $handler = $this->types->get($field->type);
        $node = new PredicateNode(
            $node->field,
            $node->operator,
            $this->normalizeQueryValue($handler, $field, $node->operator, $node->value),
        );
        $compiled = $handler->compileQuery(new QueryPredicate($field, $node->operator, $node->value));
        $strategy = $compiled->strategy;
        if ($strategy === 'jsonb'
            && ($field->metadata['unique'] ?? false) === true
            && isset($uniqueProjections[$field->id])
            && in_array($node->operator, ['eq', 'in'], true)) {
            $strategy = 'unique_projection';
        } elseif ($strategy === 'jsonb' && $this->hasAppliedExpressionIndex($field, $expressionIndexes)) {
            $strategy = 'expression_index';
        } elseif ($strategy === 'jsonb' && $node->operator === 'eq' && ! $field->multiValued && ! str_contains($field->path, '.')) {
            $strategy = 'jsonb_containment';
        } elseif ($strategy === 'jsonb') {
            if (! $spec->allowScan) {
                $this->rejectOrWarn("Unindexed predicate on '{$field->path}' is disabled.", $warnings);
            }
            $strategy = 'scan';
        }
        $strategies[] = $strategy;
        $this->applyPredicate($query, $field, $node, $compiled, $strategy, $boolean);
    }

    private function applyPredicate(
        Builder $query,
        FieldDefinition $field,
        PredicateNode $node,
        CompiledPredicate $compiled,
        string $strategy,
        string $boolean,
    ): void {
        $method = $boolean === 'or' ? 'orWhere' : 'where';
        if ($strategy === 'ref_edge' || $strategy === 'media_edge') {
            $table = $strategy === 'ref_edge' ? 'dp_ref_edges' : 'dp_media_edges';
            $targetColumn = $strategy === 'ref_edge' ? 'target_record_id' : 'media_id';
            $query->{$method}(function (Builder $nested) use ($table, $targetColumn, $field, $node): void {
                if ($node->operator === 'is_null') {
                    $nested->whereNotExists(fn (Builder $edge): Builder => $edge->from($table.' as e')
                        ->whereColumn('e.source_record_id', 'r.id')->where('e.field_id', $field->id));

                    return;
                }
                if ($node->operator === 'is_not_null') {
                    $nested->whereExists(fn (Builder $edge): Builder => $edge->from($table.' as e')
                        ->whereColumn('e.source_record_id', 'r.id')->where('e.field_id', $field->id));

                    return;
                }
                $values = $node->operator === 'in' ? (array) $node->value : [$node->value];
                $nested->whereExists(fn (Builder $edge): Builder => $edge->from($table.' as e')
                    ->whereColumn('e.source_record_id', 'r.id')
                    ->where('e.field_id', $field->id)
                    ->whereIn('e.'.$targetColumn, $values));
            });

            return;
        }
        if ($strategy === 'unique_projection') {
            $values = $node->operator === 'in' ? (array) $node->value : [$node->value];
            $hashes = array_map($this->canonicalJson->hash(...), $values);
            $query->{$method}(function (Builder $nested) use ($field, $hashes): void {
                $nested->whereExists(fn (Builder $unique): Builder => $unique->from('dp_unique_values as u')
                    ->whereColumn('u.record_id', 'r.id')
                    ->where('u.field_id', $field->id)
                    ->whereIn('u.value_hash', $hashes));
            });

            return;
        }
        if ($field->multiValued) {
            $this->applyMultiValuePredicate($query, $field, $node, $compiled, $boolean);

            return;
        }
        if ($strategy === 'jsonb_containment') {
            $query->{$method.'Raw'}('r.data @> ?::jsonb', [$this->json->encode([$field->path => $node->value])]);

            return;
        }

        $expr = $this->expressions->text($field);
        $cast = $compiled->cast;
        if ($cast !== null) {
            $expr = "({$expr})::{$cast}";
        }
        $rawMethod = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
        match ($node->operator) {
            'eq' => $query->{$rawMethod}("{$expr} = ?", [$node->value]),
            'in' => $this->whereInRaw($query, $expr, (array) $node->value, $boolean),
            'lt' => $query->{$rawMethod}("{$expr} < ?", [$node->value]),
            'lte' => $query->{$rawMethod}("{$expr} <= ?", [$node->value]),
            'gt' => $query->{$rawMethod}("{$expr} > ?", [$node->value]),
            'gte' => $query->{$rawMethod}("{$expr} >= ?", [$node->value]),
            'between' => $query->{$rawMethod}("{$expr} BETWEEN ? AND ?", array_values((array) $node->value)),
            'is_null' => $query->{$rawMethod}("{$expr} IS NULL"),
            'is_not_null' => $query->{$rawMethod}("{$expr} IS NOT NULL"),
            'contains' => $query->{$rawMethod}("{$expr} ILIKE ?", ['%'.$this->escapeLike((string) $node->value).'%']),
            'starts_with' => $query->{$rawMethod}("{$expr} ILIKE ?", [$this->escapeLike((string) $node->value).'%']),
            default => throw DataPlatformInvariantViolation::because(
                'unsupported_compiled_operator',
                "Unsupported compiled operator '{$node->operator}'.",
                ['operator' => $node->operator],
            ),
        };
    }

    private function applyMultiValuePredicate(
        Builder $query,
        FieldDefinition $field,
        PredicateNode $node,
        CompiledPredicate $compiled,
        string $boolean,
    ): void {
        $rawMethod = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
        $path = $this->expressions->jsonPath($field);
        $cast = $compiled->cast;
        $value = "dp_occurrence.value #>> '{}'";
        if ($cast !== null) {
            $value = "({$value})::{$cast}";
        }
        $source = "jsonb_path_query(r.data, {$path}::jsonpath) AS dp_occurrence(value)";
        if ($node->operator === 'is_null') {
            $query->{$rawMethod}("NOT EXISTS (SELECT 1 FROM {$source} WHERE dp_occurrence.value <> 'null'::jsonb)");

            return;
        }
        if ($node->operator === 'is_not_null') {
            $query->{$rawMethod}("EXISTS (SELECT 1 FROM {$source} WHERE dp_occurrence.value <> 'null'::jsonb)");

            return;
        }
        [$condition, $bindings] = match ($node->operator) {
            'eq' => ["{$value} = ?", [$node->value]],
            'in' => $this->inCondition($value, (array) $node->value),
            'lt' => ["{$value} < ?", [$node->value]],
            'lte' => ["{$value} <= ?", [$node->value]],
            'gt' => ["{$value} > ?", [$node->value]],
            'gte' => ["{$value} >= ?", [$node->value]],
            'between' => ["{$value} BETWEEN ? AND ?", array_values((array) $node->value)],
            'contains' => ["{$value} ILIKE ?", ['%'.$this->escapeLike((string) $node->value).'%']],
            'starts_with' => ["{$value} ILIKE ?", [$this->escapeLike((string) $node->value).'%']],
            default => throw DataPlatformInvariantViolation::because(
                'unsupported_compiled_operator',
                "Unsupported compiled operator '{$node->operator}'.",
                ['operator' => $node->operator],
            ),
        };
        $query->{$rawMethod}("EXISTS (SELECT 1 FROM {$source} WHERE dp_occurrence.value <> 'null'::jsonb AND {$condition})", $bindings);
    }

    /** @return array{string,list<mixed>} */
    private function inCondition(string $expression, array $values): array
    {
        if ($values === []) {
            return ['false', []];
        }

        return [
            $expression.' IN ('.implode(',', array_fill(0, count($values), '?')).')',
            array_values($values),
        ];
    }

    private function whereInRaw(Builder $query, string $expression, array $values, string $boolean): void
    {
        $rawMethod = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
        if ($values === []) {
            $query->{$rawMethod}('false');

            return;
        }
        $query->{$rawMethod}($expression.' IN ('.implode(',', array_fill(0, count($values), '?')).')', array_values($values));
    }

    private function applySearchPredicate(Builder $query, PredicateNode $node, string $boolean): void
    {
        if (! in_array($node->operator, ['matches', 'eq'], true)
            || ! is_string($node->value)
            || trim($node->value) === '') {
            throw DataPlatformBadRequest::because(
                'invalid_search_predicate',
                'The $search predicate requires a non-empty string and matches operator.',
            );
        }
        $method = $boolean === 'or' ? 'orWhereExists' : 'whereExists';
        $term = trim($node->value);
        $query->{$method}(function (Builder $search) use ($term): void {
            $search->from('dp_search_documents as search_document')
                ->whereColumn('search_document.record_id', 'r.id')
                ->whereRaw("search_document.document @@ websearch_to_tsquery('simple', ?)", [$term]);
        });
    }

    private function applyReversePredicate(
        Builder $query,
        string $fieldId,
        int $sourceDefinitionId,
        array $readableSourceIds,
        PredicateNode $node,
        string $boolean,
    ): void {
        if (! in_array($node->operator, ['eq', 'in', 'is_null', 'is_not_null'], true)) {
            throw DataPlatformBadRequest::because(
                'invalid_reverse_reference_operator',
                'Reverse reference predicates support eq, in, is_null and is_not_null.',
                ['operator' => $node->operator],
            );
        }
        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $query->{$method}(function (Builder $nested) use ($fieldId, $sourceDefinitionId, $readableSourceIds, $node): void {
            $edge = static fn (Builder $builder): Builder => $builder->from('dp_ref_edges as reverse_edge')
                ->join('dp_records as reverse_source', 'reverse_source.id', '=', 'reverse_edge.source_record_id')
                ->whereColumn('reverse_edge.target_record_id', 'r.id')
                ->where('reverse_edge.field_id', $fieldId)
                ->where('reverse_source.record_definition_id', $sourceDefinitionId)
                ->whereNull('reverse_source.deleted_at')
                ->whereIn('reverse_edge.source_record_id', $readableSourceIds);
            if ($node->operator === 'is_null') {
                $nested->whereNotExists($edge);

                return;
            }
            if ($node->operator === 'is_not_null') {
                $nested->whereExists($edge);

                return;
            }
            $values = array_values(array_filter(
                array_map('intval', $node->operator === 'in' ? (array) $node->value : [$node->value]),
                static fn (int $id): bool => $id > 0,
            ));
            $readableSet = array_fill_keys($readableSourceIds, true);
            $values = array_values(array_filter($values, static fn (int $id): bool => isset($readableSet[$id])));
            if ($values === []) {
                $nested->whereRaw('false');

                return;
            }
            $nested->whereExists(fn (Builder $builder): Builder => $edge($builder)->whereIn('reverse_edge.source_record_id', $values));
        });
    }

    private function assertReadableReverseField(?int $actorId, string $fieldId): int
    {
        if ($fieldId === '') {
            throw DataPlatformBadRequest::because(
                'missing_reverse_reference_field_id',
                'A reverse reference predicate requires a stable field ID.',
            );
        }
        $row = DB::table('dp_fields as field')
            ->join('dp_record_definitions as definition', 'definition.id', '=', 'field.record_definition_id')
            ->join('dp_schema_fields as schema_field', function ($join): void {
                $join->on('schema_field.field_id', '=', 'field.id')
                    ->on('schema_field.schema_version_id', '=', 'definition.current_schema_version_id');
            })
            ->where('field.id', $fieldId)
            ->first(['field.record_definition_id', 'schema_field.*']);
        if ($row === null || (string) $row->type !== FieldType::REF->value) {
            throw DataPlatformResourceNotFound::for('reverse-reference-field', $fieldId);
        }
        $definitionId = (int) $row->record_definition_id;
        $field = collect($this->schemas->fields((string) $row->schema_version_id))->first(
            static fn (FieldDefinition $candidate): bool => $candidate->id === $fieldId,
        );
        if (! $field instanceof FieldDefinition) {
            throw DataPlatformInvariantViolation::because(
                'reverse_reference_schema_field_missing',
                "Reverse reference field '{$fieldId}' is missing from its current schema.",
                ['field_id' => $fieldId],
            );
        }
        if (! $this->access->canReadDefinition($actorId, $definitionId)
            || ! $this->access->canReadField($actorId, $definitionId, $field)) {
            throw DataPlatformResourceNotFound::for('reverse-reference-field', $fieldId);
        }

        return $definitionId;
    }

    /** @return list<int> */
    private function readableReverseSourceIds(?int $actorId, int $definitionId, string $fieldId): array
    {
        $candidateIds = DB::table('dp_ref_edges as edge')
            ->join('dp_records as source', 'source.id', '=', 'edge.source_record_id')
            ->where('edge.field_id', $fieldId)
            ->where('source.record_definition_id', $definitionId)
            ->whereNull('source.deleted_at')
            ->distinct()
            ->pluck('source.id')
            ->map('intval')
            ->all();
        $maximum = max(1, (int) config('data_platform.query.max_acl_fallback_candidates'));
        if (count($candidateIds) > $maximum) {
            throw new UnindexedQueryRejected(
                'Reverse relation ACL would inspect '.count($candidateIds)." source records; maximum is {$maximum}.",
            );
        }

        return array_values(array_unique($this->access->readableTargetRecordIds($actorId, $candidateIds)));
    }

    /** @param list<FieldDefinition> $fields @return array<string,FieldDefinition> */
    private function fieldMap(array $fields): array
    {
        $map = [];
        foreach ($fields as $field) {
            $map[$field->id] = $field;
            $map[$field->path] = $field;
        }

        return $map;
    }

    /** @param array<string,FieldDefinition> $fields */
    private function field(array $fields, string $identifier): FieldDefinition
    {
        return $fields[$identifier] ?? throw DataPlatformBadRequest::because(
            'unknown_query_field',
            "Unknown query field '{$identifier}'.",
            ['field' => $identifier],
        );
    }

    private function assertReadable(?int $actorId, int $definitionId, FieldDefinition $field): void
    {
        if (! $this->access->canReadField($actorId, $definitionId, $field)) {
            throw DataAccessDenied::for('field.'.$field->id, 'query');
        }
    }

    /**
     * The search projection is one denormalized document per record built from
     * every field marked `search`, so it cannot be partitioned per field at
     * query time. Matching against it therefore reveals whether an unreadable
     * field contains a term, which is why the predicate is refused outright
     * unless the actor may read every field that feeds the projection.
     *
     * @param  array<string,FieldDefinition>  $fields
     */
    private function assertSearchable(?int $actorId, int $definitionId, array $fields): void
    {
        $checked = [];
        foreach ($fields as $field) {
            if (($field->metadata['search'] ?? false) !== true || isset($checked[$field->id])) {
                continue;
            }
            $checked[$field->id] = true;
            if (! $this->access->canReadField($actorId, $definitionId, $field)) {
                throw DataAccessDenied::for('$search', 'query');
            }
        }
    }

    private function assertGroupable(FieldDefinition $field): void
    {
        if (! in_array($field->type, [
            FieldType::BOOL,
            FieldType::DATETIME,
            FieldType::FLOAT,
            FieldType::INT,
            FieldType::STRING,
            FieldType::TEXT,
        ], true)) {
            throw DataPlatformBadRequest::because(
                'invalid_group_field',
                "Group field '{$field->path}' must be a scalar value field; refs, media and JSON cannot be grouped because that could bypass target ACL.",
                ['field_id' => $field->id, 'path' => $field->path],
            );
        }
    }

    private function assertNumericAggregateField(FieldDefinition $field): void
    {
        if ($field->type instanceof FieldType && $field->type->isNumeric()) {
            return;
        }

        throw DataPlatformBadRequest::because(
            'aggregate_field_not_numeric',
            "Field '{$field->path}' is not a numeric aggregate field.",
            ['field_id' => $field->id, 'path' => $field->path],
        );
    }

    private function normalizeQueryValue(
        FieldTypeHandler $handler,
        FieldDefinition $field,
        string $operator,
        mixed $value,
    ): mixed {
        // Query operands intentionally share write-time coercion and type errors;
        // string operators therefore also honor field trimming rules.
        if (in_array($operator, ['is_null', 'is_not_null'], true)) {
            return null;
        }

        $scalarField = $field->cardinality === Cardinality::ONE
            ? $field
            : new FieldDefinition(
                id: $field->id,
                path: $field->path,
                name: $field->name,
                type: $field->type,
                cardinality: Cardinality::ONE,
                system: $field->system,
                projectionVersion: $field->projectionVersion,
                constraints: $field->constraints,
                metadata: $field->metadata,
                parentId: $field->parentId,
                position: $field->position,
            );
        $normalize = static fn (mixed $item, int $index): mixed => $handler->normalize(
            $item,
            $scalarField,
            '$query['.$index.']',
        );
        if (in_array($operator, ['in', 'between'], true)) {
            return array_map($normalize, array_values((array) $value), array_keys(array_values((array) $value)));
        }

        return $normalize($value, 0);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function rejectOrWarn(string $message, array &$warnings): void
    {
        if ((string) config('data_platform.query.unindexed_policy') === 'fail') {
            throw new UnindexedQueryRejected($message);
        }
        $warnings[] = $message;
    }

    private function sortExpression(FieldDefinition $field): string
    {
        $expression = $field->multiValued
            ? "(SELECT dp_sort_occurrence.value #>> '{}' FROM jsonb_path_query(r.data, ".$this->expressions->jsonPath($field)."::jsonpath) AS dp_sort_occurrence(value) WHERE dp_sort_occurrence.value <> 'null'::jsonb LIMIT 1)"
            : $this->expressions->text($field);
        $cast = $this->expressions->cast($field->type);

        return $cast === null ? $expression : "({$expression})::{$cast}";
    }

    /** @param list<FieldDefinition> $fields @return array<string,true> */
    private function appliedExpressionIndexes(int $definitionId, array $fields): array
    {
        if (array_filter($fields, static fn (FieldDefinition $field): bool => ($field->metadata['indexed'] ?? false) === true) === []) {
            return [];
        }

        return DB::table('dp_projection_definitions as projection')
            ->join('dp_record_definitions as definition', 'definition.current_schema_version_id', '=', 'projection.schema_version_id')
            ->where('definition.id', $definitionId)
            ->where('projection.kind', 'expression_index')
            ->where('projection.state', ProjectionState::Applied->value)
            ->pluck('projection.field_id')
            ->mapWithKeys(static fn (mixed $fieldId): array => [(string) $fieldId => true])
            ->all();
    }

    /** @param array<string,true> $expressionIndexes */
    private function hasAppliedExpressionIndex(FieldDefinition $field, array $expressionIndexes): bool
    {
        return ($field->metadata['indexed'] ?? false) === true
            && isset($expressionIndexes[$field->id]);
    }

    /** @param list<FieldDefinition> $fields @return array<string,true> */
    private function appliedUniqueProjections(int $definitionId, array $fields): array
    {
        if (array_filter($fields, static fn (FieldDefinition $field): bool => ($field->metadata['unique'] ?? false) === true) === []) {
            return [];
        }

        return DB::table('dp_projection_definitions as projection')
            ->join('dp_record_definitions as definition', 'definition.current_schema_version_id', '=', 'projection.schema_version_id')
            ->where('definition.id', $definitionId)
            ->where('projection.kind', 'unique')
            ->where('projection.state', ProjectionState::Applied->value)
            ->pluck('projection.field_id')
            ->mapWithKeys(static fn (mixed $fieldId): array => [(string) $fieldId => true])
            ->all();
    }
}
