<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Http\Resources;

use Polymorph\Platform\Domain\AccessControl\Core\Models\Policy;
use Polymorph\Platform\Http\Resources\Admin\AdminJsonResource;

final class PolicyResource extends AdminJsonResource
{
    public function toArray($request): array
    {
        /** @var Policy $policy */
        $policy = $this->resource;

        return [
            'id'               => (int) $policy->id,
            'resource_pattern' => (string) $policy->resource_pattern,
            'action'           => (string) $policy->action,
            'effect'           => (string) $policy->effect,
            'priority'         => (int) $policy->priority,
            'is_active'        => (bool) $policy->is_active,
            'metadata'         => $policy->metadata,
            'matcher_hash'     => (string) $policy->matcher_hash,
            'created_at'       => $policy->created_at?->toDateTimeString(),
            'updated_at'       => $policy->updated_at?->toDateTimeString(),
        ];
    }
}
