<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Outbox;

final readonly class RecordEventMessage
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $type,
        public array $payload,
    ) {}
}
