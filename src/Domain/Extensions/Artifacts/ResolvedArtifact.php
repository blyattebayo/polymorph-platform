<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Artifacts;

final readonly class ResolvedArtifact
{
    public function __construct(
        public string $zipPath,
        public ?string $declaredChecksum = null,
    ) {}
}
