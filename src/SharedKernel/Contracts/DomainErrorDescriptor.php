<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Contracts;

use Polymorph\Platform\Support\Errors\ErrorCode;
use Throwable;

/**
 * Доменное исключение, которое описывает себя кодом ошибки и meta БЕЗ ErrorFactory.
 *
 * Нужно там, где исключение конвертируется не в HTTP-ответ напрямую, а в доменный
 * сбой пайплайна: шагу нужны errorCode и meta для StepResult::failure(), а тащить
 * ErrorFactory в шаг — лишняя зависимость. {@see ErrorConvertible::toError()} у тех
 * же исключений строится из этих же значений, поэтому источник правды один и статус
 * не расходится между прямым HTTP-путём (ErrorKernel) и путём через пайплайн
 * (PipelineDomainFailureHttpMapper).
 *
 * Расширяет Throwable намеренно: интерфейс имеет смысл только на исключениях, и это
 * позволяет ловить его напрямую — `catch (DomainErrorDescriptor $e)`.
 */
interface DomainErrorDescriptor extends Throwable
{
    public function errorCode(): ErrorCode;

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array;
}
