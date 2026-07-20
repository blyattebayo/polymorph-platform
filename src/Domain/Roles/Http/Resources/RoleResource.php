<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Http\Resources;

use Polymorph\Platform\Domain\AccessControl\Access\BuiltInRoleCatalog;
use Polymorph\Platform\Domain\Roles\Core\Models\Role;
use Polymorph\Platform\Http\Resources\Admin\AdminJsonResource;

final class RoleResource extends AdminJsonResource
{
    public function toArray($request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        return [
            'id' => (int) $role->id,
            'code' => (string) $role->code,
            'name' => (string) $role->name,
            'is_protected' => in_array((string) $role->code, BuiltInRoleCatalog::protectedRoleCodes(), true),
        ];
    }
}
