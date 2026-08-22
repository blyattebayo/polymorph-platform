<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Http;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\UiConfig\Core\Contracts\UiConfigDocument;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponseHeaders;

final class UiConfigResponse
{
    public static function make(?UiConfigDocument $config): JsonResponse
    {
        if ($config === null) {
            return AdminResponse::json(['data' => null]);
        }

        $response = JsonResponse::fromJsonString('{"data":'.$config->rawDocument().'}');
        AdminResponseHeaders::apply($response);

        return $response;
    }
}
