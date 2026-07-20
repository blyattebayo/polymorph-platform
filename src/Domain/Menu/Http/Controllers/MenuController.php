<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Http\Controllers;

use Polymorph\Platform\Domain\Menu\Services\MenuService;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Illuminate\Http\JsonResponse;

/**
 * Чтение настроенного меню по ключу. `data: null` — меню не настроено,
 * фронт показывает свои дефолты.
 */
final class MenuController extends Controller
{
    public function __construct(private readonly MenuService $menus)
    {
    }

    public function show(string $key): JsonResponse
    {
        return AdminResponse::json(['data' => $this->menus->get($key)]);
    }
}
