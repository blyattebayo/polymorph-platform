<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Outbox;

final readonly class DataPlatformEvent
{
    /** @param array<string,mixed> $payload @param array<string,mixed> $headers */
    public function __construct(
        public string $id,
        public string $operationId,
        public string $type,
        public string $aggregateType,
        public string $aggregateId,
        public array $payload,
        public array $headers,
    ) {}
}
