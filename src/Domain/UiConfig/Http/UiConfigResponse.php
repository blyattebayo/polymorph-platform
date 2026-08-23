<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Http;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\UiConfig\Core\Contracts\UiConfigDocument;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;

final class UiConfigResponse
{
    public static function make(?UiConfigDocument $config): JsonResponse
    {
        if ($config === null) {
            return AdminResponse::json(['data' => null, 'version' => null, 'revision' => 0]);
        }

        return AdminResponse::json([
            'data' => $config->value(),
            'version' => $config->version(),
            'revision' => $config->revision(),
        ]);
    }
}
