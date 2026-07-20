<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponseHeaders;

final class ErrorResponseFactory
{
    public static function make(ErrorPayload $payload): JsonResponse
    {
        $data = $payload->toArray();

        $meta = $data['meta'] ?? [];

        if (is_array($meta) && array_key_exists('errors', $meta) && is_array($meta['errors'])) {
            $data['errors'] = $meta['errors'];
        }

        /** @var JsonResponse $response */
        $response = response()->json($data, $payload->status, [], JSON_INVALID_UTF8_SUBSTITUTE);
        $response->headers->set('Content-Type', 'application/problem+json');

        AdminResponseHeaders::apply($response);

        return $response;
    }
}
