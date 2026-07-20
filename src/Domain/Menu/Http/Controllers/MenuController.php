<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Menu\Services\MenuService;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;

/**
 * Чтение настроенного меню по ключу. `data: null` — меню не настроено,
 * фронт показывает свои дефолты.
 */
final class MenuController extends Controller
{
    public function __construct(private readonly MenuService $menus) {}

    public function show(string $key): JsonResponse
    {
        return AdminResponse::json(['data' => $this->menus->get($key)]);
    }
}
