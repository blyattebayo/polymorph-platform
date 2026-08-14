<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Outbox;

final readonly class RecordAuditEntry
{
    /** @param list<string> $changedFieldIds @param array<string, mixed> $metadata */
    public function __construct(
        public string $operationId,
        public string $command,
        public int $recordId,
        public ?int $actorId,
        public int $revision,
        public array $changedFieldIds,
        public array $metadata,
    ) {}
}
