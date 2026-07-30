<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

use Polymorph\Platform\SharedKernel\Contracts\DescribesErrorReport;
use Throwable;

/**
 * Уровень и сообщение по умолчанию, когда у опознавшего нет специального знания.
 *
 * Правил ровно два: исключение может объявить отчёт само, иначе уровень выводится
 * из статуса ответа. Всё остальное — знание про конкретные чужие типы (SQL в
 * контексте, диагностика необёрнутых SPL) — принадлежит резолверу, который этот
 * тип опознал, и возвращается в {@see ErrorResolution::$report}.
 *
 * Так было не всегда. Сначала уровень брался как `$definition?->level ?? 'error'`,
 * и определение было null на большинстве путей — 404, 422, 401, 403 и 409 писались
 * уровнем `error`, а маппинги без своего report наследовали fallback-сообщение, из-за
 * чего штатная ошибка валидации попадала в лог как «Unhandled exception in API
 * request». Затем правила переехали сюда списком `instanceof` по базовым классам — и
 * список стал ошибаться в другую сторону: под ветвь `RuntimeException` попали 24
 * доменных исключения из 30, включая все 404 и 409, и каждое логировалось как
 * «Unwrapped RuntimeException». Списка больше нет, а вместе с ним нет и требования
 * «наследник перед предком», которое этот класс не мог выдержать в принципе:
 * доменные исключения наследуются от тех же SPL-предков, что и необёрнутые.
 */
final class ErrorReportPolicy
{
    public function for(Throwable $exception, ErrorPayload $payload): ErrorReport
    {
        // Своё объявление важнее правила по умолчанию: ошибка конфигурации JWT
        // отдаёт клиенту обычные 500, но для оператора это critical — пока secret
        // не настроен, сервис не выпустит ни один токен.
        if ($exception instanceof DescribesErrorReport) {
            return $exception->errorReport($payload);
        }

        return new ErrorReport(
            level: self::levelForStatus($payload->status),
            message: $payload->title,
        );
    }

    /**
     * Исключение, которое не опознал ни один резолвер.
     * Единственный случай, когда «Unhandled» в сообщении — правда.
     */
    public function forUnhandled(Throwable $exception, ErrorPayload $payload): ErrorReport
    {
        return new ErrorReport(
            level: 'error',
            message: 'Unhandled exception in API request',
            context: [
                'origin' => $exception::class,
                'file' => $exception->getFile().':'.$exception->getLine(),
            ],
        );
    }

    /**
     * Ровно две градации: 5xx — наша вина (`error`), 4xx — кривой запрос (`warning`).
     *
     * Разделение нужно, чтобы штатная валидация не подмешивалась к настоящим сбоям.
     * Но опускать 4xx ниже `warning` нельзя: в проде канал `api` берёт минимум из
     * `LOG_LEVEL` (см. docker/app.prod.env.example — `warning`), поэтому `info`
     * означал бы, что 401, 403 и 409 не видны вообще. Шум лечится уровнем, а не
     * потерей события.
     */
    public static function levelForStatus(int $status): string
    {
        return $status >= 500 ? 'error' : 'warning';
    }
}
