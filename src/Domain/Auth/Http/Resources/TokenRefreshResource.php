<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Resources;

use Illuminate\Http\Request;
use Polymorph\Platform\Http\Resources\Admin\AdminJsonResource;

/**
 * API Resource для ответа на успешную ротацию refresh токена.
 *
 * Возвращает сообщение об успехе. Cookies устанавливает контроллер.
 */
final class TokenRefreshResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    public function __construct()
    {
        parent::__construct(null);
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param  Request  $request  HTTP запрос
     * @return array<string, string> Массив с сообщением об успехе
     */
    public function toArray($request): array
    {
        return [
            'message' => 'Tokens refreshed successfully.',
        ];
    }
}
