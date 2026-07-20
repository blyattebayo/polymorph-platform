<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Services;

final readonly class RecordReadProfile
{
    /**
     * @param list<string> $visibleFieldPaths
     */
    public function __construct(
        public ?int $schemaId,
        public array $visibleFieldPaths,
        public bool $allowsAllFields,
    ) {
    }

    public function shouldFilterFields(): bool
    {
        return ! $this->allowsAllFields && $this->schemaId !== null;
    }
}