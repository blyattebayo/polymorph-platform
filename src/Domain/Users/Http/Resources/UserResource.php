<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Http\Resources;

use Polymorph\Platform\Http\Resources\Admin\AdminJsonResource;

/**
 * API Resource for authenticated current user payload.
 */
final class UserResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param  list<string>  $capabilities
     */
    public function __construct($resource, private readonly array $capabilities = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $user = $this->resource;

        return [
            'id' => (int) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'emailVerified' => $user->hasVerifiedEmail(),
            'capabilities' => $this->capabilities,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
