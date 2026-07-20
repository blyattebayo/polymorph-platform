<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Core\Query;

/**
 * Нормализованные критерии запроса записей одного определения, готовые к
 * трансляции в SQL репозиторием. Поля уже провалидированы по схеме, касты и
 * операторы — из белых списков (безопасно для интерполяции в выражение).
 */
final readonly class RecordQueryCriteria
{
    /**
     * @param list<RecordQueryCondition> $conditions
     * @param list<array{key: string, cast: ?string, dir: string}> $orders
     */
    public function __construct(
        public int $definitionId,
        public array $conditions,
        public ?int $authorId,
        public array $orders,
        public ?int $limit,
    ) {
    }
}
