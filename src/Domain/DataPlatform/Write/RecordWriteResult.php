<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Write;

final readonly class RecordWriteResult
{
    /** @param array<string,mixed> $document */
    public function __construct(
        public int $recordId,
        public string $schemaVersionId,
        public int $revision,
        public array $document,
        public bool $noOp,
        public string $operationId,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'record_id' => $this->recordId,
            'schema_version_id' => $this->schemaVersionId,
            'revision' => $this->revision,
            'document' => $this->document,
            'no_op' => $this->noOp,
            'operation_id' => $this->operationId,
        ];
    }

    /** @param array<string,mixed> $value */
    public static function fromArray(array $value): self
    {
        return new self(
            (int) $value['record_id'],
            (string) $value['schema_version_id'],
            (int) $value['revision'],
            is_array($value['document'] ?? null) ? $value['document'] : [],
            (bool) ($value['no_op'] ?? false),
            (string) $value['operation_id'],
        );
    }
}
