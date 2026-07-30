<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class SystemAdministratorMutationException extends \DomainException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private ?int $userId = null;

    public static function forUser(int $userId): self
    {
        $exception = new self('The system administrator account cannot be modified.');
        $exception->userId = $userId;

        return $exception;
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [
            'resource' => 'user',
            'user_id' => $this->userId,
        ];
    }
}
