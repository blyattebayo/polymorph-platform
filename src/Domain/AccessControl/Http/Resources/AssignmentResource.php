<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Http\Resources;

use Polymorph\Platform\Domain\AccessControl\Core\Models\Assignment;
use Polymorph\Platform\Http\Resources\Admin\AdminJsonResource;

final class AssignmentResource extends AdminJsonResource
{
    public function toArray($request): array
    {
        /** @var Assignment $assignment */
        $assignment = $this->resource;

        return [
            'id'        => (int) $assignment->id,
            'policy_id' => (int) $assignment->policy_id,
            'subject'   => (string) $assignment->subject,
        ];
    }
}
