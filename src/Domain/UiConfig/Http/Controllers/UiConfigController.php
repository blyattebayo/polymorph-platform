<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\UiConfig\Core\UiConfigDomain;
use Polymorph\Platform\Domain\UiConfig\Http\UiConfigResponse;
use Polymorph\Platform\Domain\UiConfig\Services\UiConfigService;
use Polymorph\Platform\Domain\UiConfig\Validation\UiConfigOperationValidator;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Транспорт превращает тело запроса в проверенную операцию и отдаёт её сервису;
 * права остаются за сервисом.
 *
 * Операция читается исключительно из JSON-тела и никогда не достраивается из
 * query или формы — иначе адрес записи можно было бы подменить строкой запроса.
 */
final class UiConfigController extends Controller
{
    public function __construct(
        private readonly UiConfigService $configs,
        private readonly UiConfigOperationValidator $operations,
    ) {}

    public function show(string $domain, string $key): JsonResponse
    {
        return UiConfigResponse::make($this->configs->load(
            $key,
            UiConfigDomain::tryFrom($domain) ?? abort(404),
        ));
    }

    public function update(Request $request): JsonResponse
    {
        return UiConfigResponse::make(
            $this->configs->save($this->operations->validateWrite($request->json()->all())),
        );
    }

    public function destroy(Request $request): Response
    {
        $this->configs->delete($this->operations->validateDelete($request->json()->all()));

        return AdminResponse::noContent();
    }
}
