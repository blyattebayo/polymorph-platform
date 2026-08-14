<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

final readonly class CompiledDisplayTemplate
{
    public function __construct(
        public string $source,
        public string $hash,
    ) {}
}
