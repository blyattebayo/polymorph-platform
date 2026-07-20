<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Ownership;

enum ResourceType: string
{
    case SCHEMA = 'schema';
    case RECORD_DEFINITION = 'record_definition';
}
