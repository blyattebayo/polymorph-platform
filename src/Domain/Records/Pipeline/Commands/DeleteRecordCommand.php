<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline\Commands;

/**
 * Command to soft-delete a record
 */
final class DeleteRecordCommand
{
    public function __construct(
        public readonly int $recordId,
        public readonly ?int $actorId = null,
        public readonly ?string $operationId = null,
    ) {}
}
