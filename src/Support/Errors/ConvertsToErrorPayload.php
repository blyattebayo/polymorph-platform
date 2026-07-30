<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;

/**
 * Реализация {@see ErrorConvertible::toError()} поверх трёх слотов исключения.
 *
 * До этого трейта каждое доменное исключение носило свою копию одной и той же
 * сборки `for()->detail()->meta()->build()` — 29 копий, различающихся только
 * кодом, деталью и meta. Копии расходились: часть отдавала `getMessage()`,
 * часть фиксированную строку, и понять правило можно было только чтением всех.
 *
 * Теперь исключение объявляет данные ({@see DomainErrorDescriptor}),
 * а сборку payload'а делает одно место. Это же даёт возможность отдать код и meta
 * в доменный сбой пайплайна (`StepResult::failure()`), где `ErrorFactory` недоступен.
 */
trait ConvertsToErrorPayload
{
    abstract public function errorCode(): ErrorCode;

    /**
     * @return array<string, mixed>
     */
    abstract public function errorMeta(): array;

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        $builder = $factory->for($this->errorCode())
            ->detail($this->errorDetail())
            ->meta($this->errorMeta());

        $title = $this->errorTitle();

        if ($title !== null) {
            $builder = $builder->title($title);
        }

        return $builder->build();
    }

    /**
     * Заголовок RFC7807. null — берётся из каталога по коду ошибки.
     *
     * Слот нужен там, где каталожный заголовок теряет смысл: у ресурсных 404
     * «Not Found» менее информативен, чем «RecordDefinition not found», и клиент
     * видит именно это поле.
     */
    protected function errorTitle(): ?string
    {
        return null;
    }

    /**
     * Текст `detail` в ответе. По умолчанию — сообщение исключения.
     *
     * Переопределяется, когда наружу отдаётся не оно: например, при ошибке
     * конфигурации JWT сообщение исключения диагностическое и в ответ ему нельзя.
     */
    protected function errorDetail(): string
    {
        return $this->getMessage();
    }
}
