<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Query;

use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;

/** Метаданные filterable поля для query criteria factory. */
final readonly class RecordQueryFieldMeta
{
    public function __construct(
        public string $fullPath,
        public FieldType $type,
        public ?string $cast,
        public bool $isIndexed,
        public bool $isUnique,
    ) {}

    public function supportsGinEquality(): bool
    {
        return ! $this->type->isContainer();
    }

    public function supportsExpressionIndex(): bool
    {
        return $this->isIndexed || $this->isUnique;
    }

    /**
     * Есть ли partial expression-индекс, чьё выражение совпадёт с типизированным
     * выражением запроса ((data_json->>'k')::cast). is_indexed строит indexExpression
     * с тем же кастом; unique-индекс теперь тоже типизирован (см. RecordIndexMaterializer).
     * Если такой индекс есть — equality выгоднее гнать по нему, а не по общему GIN.
     */
    public function hasMatchingExpressionIndex(): bool
    {
        return $this->supportsExpressionIndex();
    }

    public function allowsFilter(string $op): bool
    {
        if ($op === '=') {
            return $this->supportsGinEquality();
        }

        if ($op === 'in') {
            return $this->supportsExpressionIndex();
        }

        if (in_array($op, ['>', '>=', '<', '<=', '!=', 'isnull', 'notnull'], true)) {
            return $this->isIndexed;
        }

        return false;
    }

    public function allowsSort(): bool
    {
        return $this->isIndexed;
    }
}
