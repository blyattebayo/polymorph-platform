<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects;

/**
 * Кардинальность поля: одно значение или массив значений.
 */
enum Cardinality: string
{
    case ONE = 'one';
    case MANY = 'many';
}
