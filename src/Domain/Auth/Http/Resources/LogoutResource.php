<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Resources;

use Polymorph\Platform\Http\Resources\Admin\AdminJsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Resource для ответа на выход из системы.
 *
 * Возвращает пустой ответ со статусом 204. Cookies очищает контроллер.
 */
final class LogoutResource extends AdminJsonResource
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
     * @param  \Illuminate\Http\Request  $request  HTTP запрос
     * @return array<string, mixed> Пустой массив (тело ответа будет null)
     */
    public function toArray($request): array
    {
        return [];
    }

    /**
     * Настроить HTTP ответ для Logout.
     *
     * Устанавливает статус 204 (No Content).
     *
     * @param  \Illuminate\Http\Request  $request  HTTP запрос
     * @param  \Symfony\Component\HttpFoundation\Response  $response  HTTP ответ
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        $response->setStatusCode(Response::HTTP_NO_CONTENT);
        $response->setContent(null);

        parent::prepareAdminResponse($request, $response);
    }
}
