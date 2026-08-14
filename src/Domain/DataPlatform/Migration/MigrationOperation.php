<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Migration;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;

/** One validated declarative schema-migration operation. */
final readonly class MigrationOperation
{
    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $kind,
        public array $arguments,
    ) {
        if (! in_array($kind, MigrationOperationExecutor::OPERATIONS, true)) {
            throw DataPlatformBadRequest::because(
                'unsupported_migration_operation',
                "Unsupported migration operation '{$kind}'.",
                ['operation' => $kind],
            );
        }
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $kind = trim((string) ($input['op'] ?? ''));
        unset($input['op']);

        return new self($kind, $input);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['op' => $this->kind, ...$this->arguments];
    }

    public function argument(string $name): mixed
    {
        return $this->arguments[$name] ?? null;
    }
}
