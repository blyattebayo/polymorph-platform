<?php

namespace Polymorph\Platform\Domain\Records\Pipeline\Core;

use InvalidArgumentException;

/**
 * Record identifier value object
 */
final class RecordId
{
    private function __construct(
        public readonly int $value
    ) {
        if ($value <= 0) {
            throw new InvalidArgumentException("Record ID must be positive, got: {$value}");
        }
    }

    public static function fromInt(int $id): self
    {
        return new self($id);
    }

    public function equals(?RecordId $other): bool
    {
        return $other !== null && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
