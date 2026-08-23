<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Запись опоздала: конфиг изменился после того, как клиент его прочитал.
 *
 * Одной версии формата для этого мало — два редактора одного меню держат одну и
 * ту же версию, поэтому её guard такие записи не различает и второй молча
 * затирал первого. Ревизия различает состояния.
 */
final class UiConfigRevisionMismatchException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly int $storedRevision,
        private readonly int $submittedRevision,
    ) {
        parent::__construct('The stored UI config changed since it was read.');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    /**
     * Клиенту нужна актуальная ревизия, чтобы перечитать состояние и повторить
     * запись.
     *
     * @return array<string, int>
     */
    public function errorMeta(): array
    {
        return [
            'stored_revision' => $this->storedRevision,
            'submitted_revision' => $this->submittedRevision,
        ];
    }
}
