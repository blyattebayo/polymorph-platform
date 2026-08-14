<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Query;

final readonly class CompiledPredicate
{
    public function __construct(
        public string $strategy,
        public ?string $cast,
    ) {}
}
