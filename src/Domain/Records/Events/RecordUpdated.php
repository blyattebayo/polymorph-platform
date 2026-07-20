<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Events;

use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordSnapshot;

final class RecordUpdated
{
    public function __construct(
        public readonly RecordSnapshot $before,
        public readonly RecordSnapshot $after,
    ) {}
}