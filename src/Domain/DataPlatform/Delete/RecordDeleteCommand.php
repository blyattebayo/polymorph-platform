<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Delete;

final readonly class RecordDeleteCommand
{
    public const KIND = 'record.delete';

    public function __construct(
        public int $recordId,
        public ?int $actorId,
        public int $expectedRevision,
        public ?string $idempotencyKey = null,
    ) {}

    public function kind(): string
    {
        return self::KIND;
    }
}
