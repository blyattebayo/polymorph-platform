<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Polymorph\Platform\Http\Resources\Admin\Concerns\ConfiguresAdminResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Базовый класс для коллекций ресурсов админ-панели.
 *
 * Автоматически применяет стандартные заголовки админ-ответа.
 */
abstract class AdminResourceCollection extends ResourceCollection
{
    use ConfiguresAdminResponse;

    /**
     * Настроить HTTP ответ перед отправкой.
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
     * @param  Request  $request  HTTP запрос
     * @param  Response  $response  HTTP ответ
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        $this->addAdminResponseHeaders($response);
    }
}
