<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Resources\Admin;

use Illuminate\Http\Request;

/**
 * API Resource для тестового эндпоинта /admin/ping.
 *
 * Возвращает простой payload для проверки работы роутинга.
 */
class AdminPingResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @param  array<string, string>  $payload  Payload ответа (status, message, route)
     */
    public function __construct(private readonly array $payload)
    {
        parent::__construct(null);
    }

    /**
     * Преобразовать ресурс в массив.
     *
     * @param  Request  $request  HTTP запрос
     * @return array<string, string> Payload ответа
     */
    public function toArray($request): array
    {
        return $this->payload;
    }
}
