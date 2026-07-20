<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Observability;

use Ramsey\Uuid\Uuid;

readonly class OperationId
{
    private function __construct(public string $value) {}

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
