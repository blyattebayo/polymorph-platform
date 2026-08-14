<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Write;

final readonly class StoredRecord
{
    /** @param array<string, mixed> $document */
    public function __construct(
        public int $id,
        public int $definitionId,
        public string $schemaVersionId,
        public array $document,
        public int $revision,
        public ?int $authorId,
    ) {}
}
