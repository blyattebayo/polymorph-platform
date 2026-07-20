<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Resources\Admin;

use Polymorph\Platform\Http\Resources\Admin\Concerns\ConfiguresAdminResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Базовый класс для коллекций ресурсов админ-панели.
 *
 * Автоматически применяет стандартные заголовки админ-ответа.
 *
 * @package Polymorph\Platform\Http\Resources\Admin
 */
abstract class AdminResourceCollection extends ResourceCollection
{
    use ConfiguresAdminResponse;

    /**
     * Настроить HTTP ответ перед отправкой.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    public function withResponse($request, $response): void
    {
        $this->prepareAdminResponse($request, $response);
    }

    /**
     * Точка расширения для потомков.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Symfony\Component\HttpFoundation\Response $response HTTP ответ
     * @return void
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        $this->addAdminResponseHeaders($response);
    }
}


