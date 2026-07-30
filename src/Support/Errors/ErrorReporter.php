<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Polymorph\Platform\Support\Logging\LogChannel;
use Polymorph\Platform\Support\Logging\TraceId;
use Throwable;

/**
 * Пишет ошибку в лог по решению {@see ErrorReportPolicy} или опознавшего резолвера.
 *
 * Логгер инжектится, а не берётся через app(): раньше сервис-локатор внутри
 * статического метода делал невозможным юнит-тест пути обработки ошибок без
 * поднятого приложения.
 */
final class ErrorReporter
{
    public function __construct(
        private readonly AppLogger $logger,
        private readonly TraceId $traceId,
    ) {}

    public function report(Throwable $throwable, ErrorPayload $payload, ?ErrorReport $report = null): void
    {
        // Отчёт по умолчанию — из статуса, а НЕ жёсткий 'error': именно из-за
        // прежнего дефолта каждый 404 и 422 попадал в лог как ошибка.
        $report ??= new ErrorReport(
            level: ErrorReportPolicy::levelForStatus($payload->status),
            message: $payload->title,
        );

        $context = array_filter([
            // Контекст отчёта — первым, чтобы факты payload'а его перекрывали, а не
            // наоборот: ключ вроде 'status' в диагностике не должен подменять статус.
            ...$report->context,
            'event' => 'api.exception',
            'exception' => $throwable,
            'error_code' => $payload->code->value,
            'error_type' => $payload->type,
            'status' => $payload->status,
            'detail' => $payload->detail,
            'meta' => $payload->meta,
            // Свой trace_id у payload'а появляется только к моменту рендера ответа,
            // а лог пишется раньше — поэтому берём тот же scoped-идентификатор
            // напрямую. Без этого в лог всегда ехал null и сопоставить запись с
            // ответом клиента было нечем, хотя в теле и заголовке id уже был.
            'trace_id' => $payload->traceId ?? $this->traceId->value(),
        ], static fn (mixed $value): bool => $value !== null);

        $this->logger->channel(LogChannel::API)->log($report->level, $report->message, $context);
    }
}
