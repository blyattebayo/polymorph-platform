<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

final class RoleNotFoundException extends RuntimeException implements ErrorConvertible
{
    private function __construct(
        string $message,
        private readonly ?int $roleId = null,
    ) {
        parent::__construct($message);
    }

    public static function byId(int $roleId): self
    {
        return new self('Role not found.', $roleId);
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::NOT_FOUND)
            ->detail($this->getMessage())
            ->meta([
                'resource' => 'role',
                'role_id' => $this->roleId,
            ])
            ->build();
    }
}
