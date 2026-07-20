<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Artifacts\Contracts;

use Polymorph\Platform\Domain\Extensions\Artifacts\ResolvedArtifact;
use Polymorph\Platform\Domain\Extensions\Core\Exceptions\ExtensionException;

interface ExtensionArtifactSource
{
    /**
     * @throws ExtensionException
     */
    public function resolve(): ResolvedArtifact;
}
