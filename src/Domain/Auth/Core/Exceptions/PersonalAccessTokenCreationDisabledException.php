<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Исключение: выпуск персональных токенов выключен настройкой
 * (PAT_CREATION_ENABLED=false), при этом отзыв и просмотр остаются доступны.
 *
 * Раньше на этом месте стоял голый \RuntimeException в двух use-case'ах: он
 * доезжал до подстраховочной ветви резолвера ошибок и отдавал клиенту 500 —
 * то есть штатно выключенная фича выглядела как поломка сервиса.
 */
final class PersonalAccessTokenCreationDisabledException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public static function make(): self
    {
        return new self('Personal access token creation is disabled.');
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
        return ['reason' => 'pat_creation_disabled'];
    }
}
