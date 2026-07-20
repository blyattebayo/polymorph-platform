<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Http\Resources;

use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Http\Resources\Admin\AdminJsonResource;

final class AdminUserResource extends AdminJsonResource
{
    public static $wrap = null;

    /**
     * @param array<int, array<string, mixed>> $roles
     */
    public function __construct(User $resource, private readonly array $roles = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'status' => (string) ($user->status ?? User::STATUS_ACTIVE),
            'roles' => $this->roles,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
