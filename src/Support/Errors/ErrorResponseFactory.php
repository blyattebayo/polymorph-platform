<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponseHeaders;
use Polymorph\Platform\Support\Logging\TraceId;

/**
 * Единственное место, где payload превращается в HTTP-ответ.
 *
 * Заголовки выводятся из payload'а, а не из класса исключения. Раньше это решение
 * было размазано на четыре механизма: замыкание внутри HttpErrorException, вывод из
 * `$payload->status` в ветви расширений, вывод из `$e instanceof AuthenticationException`
 * в ветви ядра и `throwErrorWithHeaders()` в трейте. Расхождение стоило двух
 * дефектов: 503 от Symfony отдавал `retry_after` в теле и терял заголовок
 * `Retry-After`, а свои 401 (`AuthSessionUnauthorizedException`, `TokenExpiredException`,
 * `JwtVerificationException`) приезжали без `WWW-Authenticate`, обязательного по
 * RFC 7235 — при том что тот же 401 из middleware его ставил.
 */
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

        /** @var JsonResponse $response */
        $response = response()->json($data, $payload->status, [], JSON_INVALID_UTF8_SUBSTITUTE);
        $response->headers->set('Content-Type', 'application/problem+json');

        self::applyPayloadHeaders($response, $payload);

        AdminResponseHeaders::apply($response);

        return $response;
    }

    /**
     * Транспортные заголовки, выводимые из содержимого ответа.
     */
    private static function applyPayloadHeaders(JsonResponse $response, ErrorPayload $payload): void
    {
        // Тот же id уезжает заголовком: клиент не обязан парсить тело, чтобы его назвать.
        if ($payload->traceId !== null) {
            $response->headers->set('X-Trace-ID', $payload->traceId);
        }

        // RFC 7235: 401 без WWW-Authenticate — некорректный ответ. Схема одна на всё
        // API (Bearer), поэтому решение выводится из статуса, а не из типа исключения.
        if ($payload->status === 401) {
            $response->headers->set('WWW-Authenticate', 'Bearer');
            $response->headers->set('Pragma', 'no-cache');
        }

        $retryAfter = $payload->meta['retry_after'] ?? null;
        if (is_int($retryAfter) || (is_string($retryAfter) && $retryAfter !== '')) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }
    }

    /**
     * Единственное место, где trace_id попадает в тело ответа.
     *
     * Проставляется здесь, а не при сборке payload'а: через эту фабрику проходят
     * все пути (ядро, HttpErrorException, SDK-ошибки расширений), поэтому ни один
     * не может «забыть» его. До этого поле присутствовало в контракте RFC7807, но
     * никто его не заполнял — в каждом ответе ехал null.
     *
     * Тот же scoped-идентификатор берёт и {@see ErrorReporter}, который пишет лог
     * раньше рендера, поэтому значения в логе и в ответе совпадают.
     */
    private static function withTraceId(ErrorPayload $payload): ErrorPayload
    {
        if ($payload->traceId !== null) {
            return $payload;
        }

        return $payload->withTraceId(app(TraceId::class)->value());
    }
}
