<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\UiConfig\Core\ConfigNamespace;
use Polymorph\Platform\Domain\UiConfig\Http\Requests\SaveUiConfigRequest;
use Polymorph\Platform\Domain\UiConfig\Http\Requests\UiConfigWriteRequest;
use Polymorph\Platform\Domain\UiConfig\Http\UiConfigResponse;
use Polymorph\Platform\Domain\UiConfig\Services\ConfigService;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Один вход для всех видов UI-конфига. Чтение адресуется путём — это адрес
 * ресурса; запись и удаление получают адрес в теле, вместе с ревизией.
 */
final class ConfigController extends Controller
{
    public function __construct(
        private readonly ConfigService $configs,
    ) {}

    public function show(string $namespace, string $key): JsonResponse
    {
        return UiConfigResponse::make($this->configs->show(
            ConfigNamespace::tryFrom($namespace) ?? abort(404),
            $key,
        ));
    }

    public function update(SaveUiConfigRequest $request): JsonResponse
    {
        return UiConfigResponse::make($this->configs->save(
            $request->configNamespace(),
            $request->key(),
            $request->revision(),
            $request->version(),
            $request->document(),
        ));
    }

    public function destroy(UiConfigWriteRequest $request): Response
    {
        $this->configs->delete($request->configNamespace(), $request->key(), $request->revision());

        return AdminResponse::noContent();
    }
}
