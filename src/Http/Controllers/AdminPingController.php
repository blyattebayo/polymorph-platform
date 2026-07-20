<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Controllers;

use Polymorph\Platform\Http\Resources\Admin\AdminPingResource;

/**
 * Тестовый контроллер для проверки порядка роутинга.
 *
 * Маршрут /admin/ping должен обрабатываться до fallback,
 * что подтверждает правильный порядок загрузки роутов.
 *
 * @package Polymorph\Platform\Http\Controllers
 */
class AdminPingController extends Controller
{
    /**
     * GET /admin/ping
     *
     * Простой эндпоинт для проверки, что системные маршруты
     * обрабатываются раньше fallback.
     *
     * @group Admin • System
     * @name Ping
     * @unauthenticated
     * @response status=200 {
     *   "status": "OK",
     *   "message": "Admin ping route is working",
     *   "route": "/admin/ping"
     * }
     */
    public function ping(): AdminPingResource
    {
        return new AdminPingResource([
            'status' => 'OK',
            'message' => 'Admin ping route is working',
            'route' => '/admin/ping',
        ]);
    }
}

