<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Materialization\Services;

final class SqlJoinContext
{
    /** @var list<array{alias:string,condition:string}> */
    private array $joins = [];
    private int $counter = 0;

    public function nextAlias(): string
    {
        $this->counter++;

        return 'e' . $this->counter;
    }

    public function addLeftJoin(string $alias, string $condition): void
    {
        $this->joins[] = [
            'alias' => $alias,
            'condition' => $condition,
        ];
    }

    public function renderWithJoins(string $projectionExpr): string
    {
        $sql = "(SELECT {$projectionExpr} FROM records e";

        foreach ($this->joins as $join) {
            $sql .= " LEFT JOIN records {$join['alias']} ON {$join['condition']}";
        }

        $sql .= ' WHERE e.id = src.id)';

        return $sql;
    }
}
