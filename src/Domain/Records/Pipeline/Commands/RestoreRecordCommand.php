<?php

namespace Polymorph\Platform\Domain\Records\Pipeline\Commands;

final class RestoreRecordCommand
{
    public function __construct(
        public readonly int $recordId,
        public readonly ?int $actorId = null,
        public readonly ?string $operationId = null,
    ) {}
}
