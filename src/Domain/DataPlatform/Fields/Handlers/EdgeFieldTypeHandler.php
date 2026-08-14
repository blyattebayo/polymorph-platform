<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Query\CompiledPredicate;
use Polymorph\Platform\Domain\DataPlatform\Query\QueryPredicate;

/** Shared query and row-envelope contract for reference-like projections. */
abstract class EdgeFieldTypeHandler extends AbstractFieldTypeHandler
{
    final public function compileQuery(QueryPredicate $predicate): CompiledPredicate
    {
        $this->assertSupportedQueryOperator(
            $predicate,
            $this->unsupportedOperatorReason(),
            "Unsupported {$this->operatorSubject()} operator '{$predicate->operator}'.",
        );

        return new CompiledPredicate(strategy: $this->edgeStrategy(), cast: null);
    }

    /**
     * @param  callable(mixed):array<string,mixed>  $specific
     * @return list<array<string,mixed>>
     */
    protected function edgeRows(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
        callable $specific,
    ): array {
        $rows = [];
        foreach ($this->values($value, $field) as $position => $item) {
            $rows[] = [
                'field_id' => $field->id,
                'occurrence' => $occurrence,
                'position' => $position,
                ...$specific($item),
                'projection_version' => $field->projectionVersion,
            ];
        }

        return $rows;
    }

    abstract protected function edgeStrategy(): string;

    abstract protected function unsupportedOperatorReason(): string;

    abstract protected function operatorSubject(): string;
}
