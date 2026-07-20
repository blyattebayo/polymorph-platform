<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use Exception;

/**
 * Исключение выбрасывается при обнаружении циклической зависимости
 * в иерархии узлов маршрутов.
 *
 * @package Polymorph\Platform\Domain\Routing\Exceptions
 */
class CyclicDependencyException extends Exception implements ErrorConvertible
{
    public function __construct(string $message = 'Cyclic dependency detected', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::CONFLICT)
            ->detail($this->getMessage())
            ->build();
    }
}
