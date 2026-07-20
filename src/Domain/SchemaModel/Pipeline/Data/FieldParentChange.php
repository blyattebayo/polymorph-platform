<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Data;

final readonly class FieldParentChange
{
    private function __construct(
        public bool $changed,
        public ?int $targetParentId,
    ) {}

    public static function unchanged(): self
    {
        return new self(false, null);
    }

    public static function toRoot(): self
    {
        return new self(true, null);
    }

    public static function to(int $parentId): self
    {
        return new self(true, $parentId);
    }
}
