<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\TableConfig\Services\TableConfigService;
use Polymorph\Platform\Domain\UiConfig\Http\Requests\SaveUiConfigRequest;
use Polymorph\Platform\Domain\UiConfig\Http\UiConfigResponse;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Symfony\Component\HttpFoundation\Response;

final class TableConfigController extends Controller
{
    public function __construct(
        private readonly TableConfigService $tables,
        private readonly AuthenticationContext $auth,
    ) {}

    public function showGlobal(string $key): JsonResponse
    {
        return UiConfigResponse::make($this->tables->showGlobal($key));
    }

    public function updateGlobal(SaveUiConfigRequest $request, string $key): JsonResponse
    {
        return UiConfigResponse::make($this->tables->updateGlobal(
            $key,
            $request->version(),
            $request->document(),
        ));
    }

    public function destroyGlobal(string $key): Response
    {
        $this->tables->deleteGlobal($key);

        return AdminResponse::noContent();
    }

    public function showMine(string $key): JsonResponse
    {
        return UiConfigResponse::make($this->tables->showMine($this->auth->requireUser(), $key));
    }

    public function updateMine(SaveUiConfigRequest $request, string $key): JsonResponse
    {
        return UiConfigResponse::make($this->tables->updateMine(
            $this->auth->requireUser(),
            $key,
            $request->version(),
            $request->document(),
        ));
    }

    public function destroyMine(string $key): Response
    {
        $this->tables->deleteMine($this->auth->requireUser(), $key);

        return AdminResponse::noContent();
    }
}
