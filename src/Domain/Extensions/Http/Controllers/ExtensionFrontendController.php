<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Extensions\Http\Requests\ExtensionFrontendManifestRequest;
use Polymorph\Platform\Domain\Extensions\Services\ExtensionFrontendManifestService;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;

final class ExtensionFrontendController extends Controller
{
    public function __construct(
        private readonly ExtensionFrontendManifestService $manifestService,
    ) {}

    public function index(ExtensionFrontendManifestRequest $request): JsonResponse
    {
        return AdminResponse::json([
            'data' => $this->manifestService->enabledFrontendPlugins(),
        ]);
    }
}
