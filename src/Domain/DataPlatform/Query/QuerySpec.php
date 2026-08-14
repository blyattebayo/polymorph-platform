<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Query;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;

final readonly class QuerySpec
{
    /**
     * @param  list<array{field:string,direction:string}>  $sort
     * @param  list<string>  $include
     * @param  list<string>  $groupBy
     * @param  array<string,mixed>|null  $aggregate
     */
    public function __construct(
        public int $recordDefinitionId,
        public ?FilterNode $filter = null,
        public array $sort = [],
        public int $page = 1,
        public int $perPage = 50,
        public array $include = [],
        public ?array $aggregate = null,
        public array $groupBy = [],
        public bool $allowScan = false,
    ) {
        if ($recordDefinitionId <= 0) {
            throw DataPlatformBadRequest::because('invalid_record_definition_id', 'recordDefinitionId must be positive.');
        }
        if ($page < 1 || $perPage < 1 || $perPage > 500) {
            throw DataPlatformBadRequest::because(
                'invalid_query_pagination',
                'Invalid query pagination.',
                ['page' => $page, 'per_page' => $perPage],
            );
        }
        if (count($groupBy) > 5 || array_filter($groupBy, static fn (mixed $field): bool => ! is_string($field) || trim($field) === '') !== []) {
            throw DataPlatformBadRequest::because(
                'invalid_group_by',
                'groupBy accepts at most five non-empty field identifiers.',
            );
        }
        if ($groupBy !== [] && $aggregate === null) {
            throw DataPlatformBadRequest::because('group_by_requires_aggregate', 'groupBy requires an aggregate.');
        }
    }

    /** @param array<string,mixed> $value */
    public static function fromArray(array $value): self
    {
        $sort = [];
        foreach (($value['sort'] ?? []) as $entry) {
            if (! is_array($entry) || ! is_string($entry['field'] ?? null)) {
                throw DataPlatformBadRequest::because('invalid_sort_entry', 'Invalid sort entry.');
            }
            $sort[] = [
                'field' => $entry['field'],
                'direction' => strtolower((string) ($entry['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
            ];
        }

        return new self(
            recordDefinitionId: (int) ($value['record_definition_id'] ?? 0),
            filter: isset($value['filter']) ? self::parseFilter($value['filter']) : null,
            sort: $sort,
            page: max(1, (int) ($value['page'] ?? 1)),
            perPage: max(1, min(500, (int) ($value['per_page'] ?? 50))),
            include: array_values(array_filter((array) ($value['include'] ?? []), 'is_string')),
            aggregate: is_array($value['aggregate'] ?? null) ? $value['aggregate'] : null,
            groupBy: array_values(array_filter((array) ($value['group_by'] ?? []), 'is_string')),
            allowScan: (bool) ($value['allow_scan'] ?? false),
        );
    }

    private static function parseFilter(mixed $value): FilterNode
    {
        if (! is_array($value)) {
            throw DataPlatformBadRequest::because('invalid_filter', 'Filter must be an object.');
        }
        foreach (['and', 'or'] as $operator) {
            if (array_key_exists($operator, $value)) {
                if (! is_array($value[$operator]) || ! array_is_list($value[$operator])) {
                    throw DataPlatformBadRequest::because(
                        'invalid_boolean_filter',
                        "{$operator} must be a list.",
                        ['operator' => $operator],
                    );
                }

                return new BooleanNode($operator, array_map(self::parseFilter(...), $value[$operator]));
            }
        }
        if (array_key_exists('not', $value)) {
            return new BooleanNode('not', [self::parseFilter($value['not'])]);
        }
        if (! is_string($value['field'] ?? null) || ! is_string($value['op'] ?? null)) {
            throw DataPlatformBadRequest::because('invalid_predicate', 'A predicate requires field and op.');
        }

        return new PredicateNode($value['field'], strtolower($value['op']), $value['value'] ?? null);
    }
}
