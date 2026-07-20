<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Resources;

use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Domain\Users\Http\Resources\UserResource;
use Polymorph\Platform\Http\Resources\Admin\AdminJsonResource;

/**
 * API Resource для ответа на успешный вход в систему.
 *
 * Возвращает данные пользователя. Cookies устанавливает контроллер.
 */
class LoginResource extends AdminJsonResource
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
    public function __construct(
        private readonly User $user,
        private readonly array $capabilities = []
    ) {
        parent::__construct(null);
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param  Request  $request  HTTP запрос
     * @return array<string, array<string, mixed>> Массив с данными пользователя
     */
    public function toArray($request): array
    {
        $base = (new UserResource($this->user, $this->capabilities))->resolve();

        return [
            'user' => [
                'id' => (int) $base['id'],
                'email' => (string) $base['email'],
                'name' => (string) $base['name'],
                'emailVerified' => (bool) $base['emailVerified'],
                'capabilities' => $base['capabilities'],
            ],
        ];
    }
}
