<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Resources\Admin\Concerns;

use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponseHeaders;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait для настройки HTTP ответов админ-панели.
 *
 * Применяет стандартные заголовки для всех админских ресурсов
 * через AdminResponseHeaders.
 */
trait ConfiguresAdminResponse
{
    /**
     * Установить стандартные заголовки для админских ответов.
     *
     * @param  Response  $response  HTTP ответ
     */
    protected function addAdminResponseHeaders(Response $response): void
    {
        AdminResponseHeaders::apply($response);
    }
}
