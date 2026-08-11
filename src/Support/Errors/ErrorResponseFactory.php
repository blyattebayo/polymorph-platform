<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponseHeaders;
use Polymorph\Platform\Support\Logging\TraceId;

/** The single transport boundary that turns an error payload into an HTTP response. */
final class ErrorResponseFactory
{
    public static function make(ErrorPayload $payload): JsonResponse
    {
        $payload = self::withTraceId($payload);
        $data = $payload->toArray();
        $meta = $data['meta'] ?? [];

        if (is_array($meta) && array_key_exists('errors', $meta) && is_array($meta['errors'])) {
            $data['errors'] = $meta['errors'];
        }
        if (isset($data['meta']) && is_array($data['meta'])) {
            unset($data['meta']['www_authenticate']);
        }

        /** @var JsonResponse $response */
        $response = response()->json($data, $payload->status, [], JSON_INVALID_UTF8_SUBSTITUTE);
        $response->headers->set('Content-Type', 'application/problem+json');

        self::applyPayloadHeaders($response, $payload);
        AdminResponseHeaders::apply($response);

        return $response;
    }

    private static function applyPayloadHeaders(JsonResponse $response, ErrorPayload $payload): void
    {
        if ($payload->traceId !== null) {
            $response->headers->set('X-Trace-ID', $payload->traceId);
        }

        // RFC 7235 requires this header on every 401 response.
        if ($payload->status === 401) {
            $challenge = $payload->meta['www_authenticate'] ?? 'Bearer';
            $response->headers->set('WWW-Authenticate', is_string($challenge) ? $challenge : 'Bearer');
            $response->headers->set('Pragma', 'no-cache');
        }

        $retryAfter = $payload->meta['retry_after'] ?? null;
        if (is_int($retryAfter) || (is_string($retryAfter) && $retryAfter !== '')) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }
    }

    private static function withTraceId(ErrorPayload $payload): ErrorPayload
    {
        if ($payload->traceId !== null) {
            return $payload;
        }

        return $payload->withTraceId(app(TraceId::class)->value());
    }
}
