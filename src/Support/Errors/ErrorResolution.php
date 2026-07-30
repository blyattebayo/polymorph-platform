<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

/**
 * Результат опознания исключения: во что оно превращается и, при необходимости,
 * как именно его писать в лог.
 *
 * Второе поле нужно, потому что только опознавший знает контекст: `QueryException`
 * стоит логировать вместе с SQL, а необёрнутое SPL-исключение — с пометкой
 * «происхождение неизвестно, место надо завернуть». Раньше эти решения жили в
 * ErrorReportPolicy списком `instanceof` по базовым классам, и он ошибался на всём,
 * что унаследовано от RuntimeException: 24 доменных исключения из 30 писались в лог
 * как «Unwrapped RuntimeException», включая штатные 404 и 409.
 *
 * `report === null` — «специального знания нет», и тогда уровень выводится из
 * статуса ответа {@see ErrorReportPolicy::for()}.
 */
final readonly class ErrorResolution
{
    public function __construct(
        public ErrorPayload $payload,
        public ?ErrorReport $report = null,
    ) {}
}
