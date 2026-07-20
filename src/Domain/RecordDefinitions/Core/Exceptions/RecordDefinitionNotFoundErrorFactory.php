<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Core\Exceptions;

use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\HttpErrorException;

final class RecordDefinitionNotFoundErrorFactory
{
    public static function byId(ErrorFactory $errorFactory, int $id): HttpErrorException
    {
        $payload = $errorFactory
            ->for(ErrorCode::NOT_FOUND)
            ->title('RecordDefinition not found')
            ->detail("RecordDefinition not found: {$id}")
            ->meta(['id' => $id])
            ->build();

        return new HttpErrorException($payload);
    }
}
