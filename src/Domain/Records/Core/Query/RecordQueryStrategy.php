<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Core\Query;

enum RecordQueryStrategy: string
{
    /** Equality via global GIN: data_json @> ?::jsonb */
    case Containment = 'containment';

    /** Expression path: (data_json ->> 'key')::cast */
    case Expression = 'expression';
}
