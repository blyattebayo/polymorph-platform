<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Contracts;

use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;

interface ErrorConvertible
{
    public function toError(ErrorFactory $factory): ErrorPayload;
}
