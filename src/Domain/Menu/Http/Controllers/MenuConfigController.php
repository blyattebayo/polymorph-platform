<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Menu\Services\MenuConfigService;
use Polymorph\Platform\Domain\UiConfig\Http\Requests\SaveUiConfigRequest;
use Polymorph\Platform\Domain\UiConfig\Http\UiConfigResponse;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Symfony\Component\HttpFoundation\Response;

final class MenuConfigController extends Controller
{
    public function __construct(
        private readonly MenuConfigService $menus,
    ) {}

    public function show(string $key): JsonResponse
    {
        return UiConfigResponse::make($this->menus->show($key));
    }

    public function update(SaveUiConfigRequest $request, string $key): JsonResponse
    {
        return UiConfigResponse::make($this->menus->update(
            $key,
            $request->version(),
            $request->document(),
        ));
    }

    public function destroy(string $key): Response
    {
        $this->menus->delete($key);

        return AdminResponse::noContent();
    }
}
