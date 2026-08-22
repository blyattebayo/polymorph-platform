<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\EntryView\Http\Requests\EntryViewConfigRequest;
use Polymorph\Platform\Domain\EntryView\Services\EntryViewConfigService;
use Polymorph\Platform\Domain\UiConfig\Http\UiConfigResponse;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Symfony\Component\HttpFoundation\Response;

final class EntryViewConfigController extends Controller
{
    public function __construct(
        private readonly EntryViewConfigService $entryViews,
    ) {}

    public function show(
        EntryViewConfigRequest $request,
        string $recordDefinition,
        string $schema,
    ): JsonResponse {
        return UiConfigResponse::make($this->entryViews->show(
            (int) $recordDefinition,
            (int) $schema,
        ));
    }

    public function update(
        EntryViewConfigRequest $request,
        string $recordDefinition,
        string $schema,
    ): JsonResponse {
        return UiConfigResponse::make($this->entryViews->update(
            (int) $recordDefinition,
            (int) $schema,
            $request->version(),
            $request->document(),
        ));
    }

    public function destroy(
        EntryViewConfigRequest $request,
        string $recordDefinition,
        string $schema,
    ): Response {
        $this->entryViews->delete(
            (int) $recordDefinition,
            (int) $schema,
        );

        return AdminResponse::noContent();
    }
}
