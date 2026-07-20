<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Services;

use Polymorph\Platform\Domain\Records\Support\RecordDataNormalizer;
use Polymorph\Platform\Domain\Records\Support\RecordSystemFields;
use Polymorph\Platform\Domain\SchemaModel\Services\PathTreeFilter;

final class RecordPayloadProjector
{
    public function __construct(
        private readonly PathTreeFilter $pathTreeFilter,
    ) {
    }

    public function project(mixed $dataJson, RecordReadProfile $profile): object
    {
        $payload = RecordDataNormalizer::fromMixed($dataJson);

        if ($profile->shouldFilterFields()) {
            $payload = $this->pathTreeFilter->filterVisibleData($payload, $profile->visibleFieldPaths);
        }

        return (object) RecordSystemFields::normalizeForRead($payload);
    }
}