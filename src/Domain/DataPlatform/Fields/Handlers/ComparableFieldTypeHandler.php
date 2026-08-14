<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

/** Shared query capability for scalar values with a total ordering. */
abstract class ComparableFieldTypeHandler extends AbstractFieldTypeHandler
{
    private const ORDERED_OPERATORS = [
        'eq', 'in', 'lt', 'lte', 'gt', 'gte', 'between', 'is_null', 'is_not_null',
    ];

    public function supportedQueryOperators(): array
    {
        return self::ORDERED_OPERATORS;
    }
}
