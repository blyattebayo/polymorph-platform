<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Dsl;

/**
 * Единственный источник правды по операторам сравнения DSL валидации.
 * Используется и вайтлистом DslValidator, и рантайм-правилами
 * (ScopedRequiredIf, ScopedFieldComparison, CollectionQuantifier).
 */
enum DslOperator: string
{
    case Eq = '==';
    case Neq = '!=';
    case Gt = '>';
    case Lt = '<';
    case Gte = '>=';
    case Lte = '<=';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Loose-сравнение операндов по правилам PHP (как в required_if/quantifier).
     */
    public function evaluate(mixed $left, mixed $right): bool
    {
        return match ($this) {
            self::Eq => $left == $right,
            self::Neq => $left != $right,
            self::Gt => $left > $right,
            self::Lt => $left < $right,
            self::Gte => $left >= $right,
            self::Lte => $left <= $right,
        };
    }

    /**
     * Интерпретация уже вычисленного spaceship-результата (left <=> right)
     * для правил, нормализующих операнды перед сравнением (field_comparison).
     */
    public function evaluateComparison(int $comparison): bool
    {
        return match ($this) {
            self::Eq => $comparison === 0,
            self::Neq => $comparison !== 0,
            self::Gt => $comparison > 0,
            self::Lt => $comparison < 0,
            self::Gte => $comparison >= 0,
            self::Lte => $comparison <= 0,
        };
    }
}
