<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Contracts;

interface MediaIncludedProvider
{
    /**
     * @param  string[]  $mediaIds
     */
    public function buildIncludedByIds(array $mediaIds): \stdClass;
}
