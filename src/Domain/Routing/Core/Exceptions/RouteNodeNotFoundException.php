<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use Exception;

/**
 * Исключение выбрасывается когда узел маршрута не найден.
 *
 * @package Polymorph\Platform\Domain\Routing\Exceptions
 */
class RouteNodeNotFoundException extends Exception implements ErrorConvertible
{
    public function __construct(string $message = 'Route node not found', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::NOT_FOUND)
            ->detail($this->getMessage())
            ->build();
    }
}
