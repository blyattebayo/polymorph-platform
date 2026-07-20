<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Extensions\Http\Requests\ExtensionLifecycleRequest;
use Polymorph\Platform\Domain\Extensions\Http\Requests\SyncExtensionsRequest;
use Polymorph\Platform\Domain\Extensions\Http\Resources\ExtensionResource;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionManager;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;

final class ExtensionAdminController extends Controller
{
    public function __construct(
        private readonly ExtensionManager $pluginManager,
    ) {}

    public function index(SyncExtensionsRequest $request): JsonResponse
    {
        $this->pluginManager->discoverAndSync();

        return ExtensionResource::collection($this->pluginManager->listRegistry())->response();
    }

    public function enable(ExtensionLifecycleRequest $request): JsonResponse
    {
        $plugin = $this->pluginManager->enable((string) $request->validated()['plugin_id']);

        return AdminResponse::json(['data' => ExtensionResource::make($plugin)->toArray($request)]);
    }

    public function disable(ExtensionLifecycleRequest $request): JsonResponse
    {
        $plugin = $this->pluginManager->disable(
            (string) $request->validated()['plugin_id'],
            (bool) ($request->validated()['force'] ?? false),
        );

        return AdminResponse::json(['data' => ExtensionResource::make($plugin)->toArray($request)]);
    }

    public function update(ExtensionLifecycleRequest $request): JsonResponse
    {
        $plugin = $this->pluginManager->update((string) $request->validated()['plugin_id']);

        return AdminResponse::json(['data' => ExtensionResource::make($plugin)->toArray($request)]);
    }
}
