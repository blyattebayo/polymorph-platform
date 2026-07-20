<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline\Core;

use InvalidArgumentException;

/**
 * Record revision value object - monotonically increasing version counter
 */
final class RecordRevision
{
    private function __construct(
        public readonly int $value
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException("Record revision cannot be negative, got: {$value}");
        }
    }

    public static function fromInt(int $revision): self
    {
        return new self($revision);
    }

    public static function initial(): self
    {
        return new self(0);
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }

    public function isGreaterThan(RecordRevision $other): bool
    {
        return $this->value > $other->value;
    }

    public function isGreaterOrEqual(RecordRevision $other): bool
    {
        return $this->value >= $other->value;
    }

    public function equals(?RecordRevision $other): bool
    {
        return $other !== null && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
