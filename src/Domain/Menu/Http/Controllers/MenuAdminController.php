<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Http\Controllers;

use Polymorph\Platform\Domain\Menu\Http\Requests\SaveMenuRequest;
use Polymorph\Platform\Domain\Menu\Services\MenuService;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Управление конфигурацией меню (билдер). Требует capability menu.manage.
 */
final class MenuAdminController extends Controller
{
    public function __construct(private readonly MenuService $menus)
    {
    }

    public function show(string $key): JsonResponse
    {
        return AdminResponse::json(['data' => $this->menus->get($key)]);
    }

    public function update(SaveMenuRequest $request, string $key): JsonResponse
    {
        return AdminResponse::json(['data' => $this->menus->save($key, $request->tree())]);
    }

    public function destroy(Request $request, string $key): JsonResponse
    {
        $this->menus->reset($key);

        return AdminResponse::json(['data' => null]);
    }
}
