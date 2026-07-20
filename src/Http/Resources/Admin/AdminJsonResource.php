<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Polymorph\Platform\Http\Resources\Admin\Concerns\ConfiguresAdminResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Базовый класс для JSON ресурсов админ-панели.
 *
 * Автоматически применяет стандартные заголовки для админских ответов
 * через trait ConfiguresAdminResponse.
 */
abstract class AdminJsonResource extends JsonResource
{
    use ConfiguresAdminResponse;

    /**
     * Настроить HTTP ответ перед отправкой.
     *
     * Вызывает prepareAdminResponse для настройки заголовков и статуса.
     *
     * @param  Request  $request  HTTP запрос
     * @param  Response  $response  HTTP ответ
     */
    public function withResponse($request, $response): void
    {
        $this->prepareAdminResponse($request, $response);
    }

    /**
     * Точка расширения для потомков.
     *
     * Позволяет переопределить логику подготовки ответа
     * (установка статуса, cookies и т.д.).
     *
     * @param  Request  $request  HTTP запрос
     * @param  Response  $response  HTTP ответ
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        $this->addAdminResponseHeaders($response);
    }
}
