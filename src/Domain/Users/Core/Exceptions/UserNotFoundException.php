<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Исключение: пользователь не найден.
 *
 * meta ресурсных 404 едина для всех доменов: {resource, identifier,
 * identifier_type}, без null-ключей под неиспользованные способы поиска.
 */
class UserNotFoundException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private function __construct(
        string $message,
        private readonly int|string $identifier,
        private readonly string $identifierType,
    ) {
        parent::__construct($message);
    }

    /**
     * Создать исключение для ID.
     */
    public static function byId(int $id): self
    {
        return new self("User with ID '{$id}' not found", $id, 'id');
    }

    /**
     * Создать исключение для email.
     */
    public static function byEmail(string $email): self
    {
        return new self("User with email '{$email}' not found", $email, 'email');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::NOT_FOUND;
    }

    protected function errorTitle(): ?string
    {
        return 'User not found';
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [
            'resource' => 'user',
            'identifier' => $this->identifier,
            'identifier_type' => $this->identifierType,
        ];
    }
}
