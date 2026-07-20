<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Artifacts\Contracts;

use Polymorph\Platform\Domain\Extensions\Artifacts\ResolvedArtifact;

interface ExtensionArtifactSource
{
    /**
     * @throws \Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException
     */
    public function resolve(): ResolvedArtifact;
}
