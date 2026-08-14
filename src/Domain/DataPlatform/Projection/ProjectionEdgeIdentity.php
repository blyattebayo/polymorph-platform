<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Polymorph\Platform\Domain\DataPlatform\Fields\OccurrencePath;

final class ProjectionEdgeIdentity
{
    /** @param array<string,mixed> $edge @return array<string,mixed> */
    public static function withItemId(array $edge): array
    {
        $edge['item_id'] = OccurrencePath::lastStableItemId((string) ($edge['occurrence'] ?? ''));

        return $edge;
    }
}
