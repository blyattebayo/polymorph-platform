<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

enum Cardinality: string
{
    case ONE = 'one';
    case MANY = 'many';
}
