<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Write;

final readonly class RecordWriteCommand
{
    /** @param array<string,mixed> $document */
    public function __construct(
        public int $recordDefinitionId,
        public array $document,
        public ?int $actorId,
        public ?int $recordId = null,
        public ?int $expectedRevision = null,
        public ?string $idempotencyKey = null,
        public ?string $schemaVersionId = null,
        public bool $replace = false,
        public bool $schemaMigration = false,
    ) {}

    public function kind(): string
    {
        return $this->recordId === null ? 'record.create' : 'record.update';
    }
}
