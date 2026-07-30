<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

final class RoleNotFoundException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private function __construct(
        string $message,
        private readonly int $roleId,
    ) {
        parent::__construct($message);
    }

    public static function byId(int $roleId): self
    {
        return new self("Role with ID '{$roleId}' not found", $roleId);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::NOT_FOUND;
    }

    protected function errorTitle(): ?string
    {
        return 'Role not found';
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [
            'resource' => 'role',
            'identifier' => $this->roleId,
            'identifier_type' => 'id',
        ];
    }
}
