<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use Exception;

/**
 * Исключение выбрасывается при попытке изменить readonly узел маршрута.
 * Декларативные и системные маршруты нельзя изменять.
 *
 * @package Polymorph\Platform\Domain\Routing\Exceptions
 */
class ReadonlyRouteNodeException extends Exception implements ErrorConvertible
{
    public function __construct(string $message = 'Cannot modify readonly route node', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::FORBIDDEN)
            ->detail($this->getMessage())
            ->build();
    }
}
