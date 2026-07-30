<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

/**
 * Встроенная учётка платформенного администратора неприкасаема через админ-API.
 *
 * Раньше это исключение бросалось для ЛЮБОГО носителя роли system.admin —
 * назначение роли превращалось в одностороннюю дверь (пользователь становился
 * нередактируемым, роль неснимаемой). Теперь предикат — флаг is_platform_admin,
 * который ставит только AdminUserSeeder.
 */
final class PlatformAdminImmutableException extends \DomainException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private ?int $userId = null;

    public static function forUser(int $userId): self
    {
        $exception = new self('The built-in platform administrator account cannot be modified.');
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
            'reason' => 'platform_admin_immutable',
            'resource' => 'user',
            'user_id' => $this->userId,
        ];
    }
}
