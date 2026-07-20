<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Operands;

final readonly class ResolvedOperand
{
    public function __construct(
        public string $original,
        public string $resolved,
        public bool $found,
    ) {}
}
