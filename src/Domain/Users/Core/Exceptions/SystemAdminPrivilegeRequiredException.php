<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

/**
 * Операция над системным администратором или ролью system.admin требует,
 * чтобы актор сам был системным администратором.
 *
 * Закрывает две дыры аудита: users.manager мог выдать system.admin кому угодно
 * (эскалация привилегий снизу вверх), а после появления снимаемости роли —
 * мог бы и «разжаловать» вышестоящего.
 */
final class SystemAdminPrivilegeRequiredException extends \DomainException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private ?int $targetUserId = null;

    public static function toChangeAdminRole(): self
    {
        return new self('Only a system administrator can grant or revoke the system administrator role.');
    }

    public static function toModifyAdministrator(int $targetUserId): self
    {
        $exception = new self('Only a system administrator can modify another system administrator.');
        $exception->targetUserId = $targetUserId;

        return $exception;
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::FORBIDDEN;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return array_filter([
            'reason' => 'system_admin_privilege_required',
            'target_user_id' => $this->targetUserId,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
