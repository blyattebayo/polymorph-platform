<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Исключение: ошибка верификации JWT токена.
 */
class JwtVerificationException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        string $message,
        public readonly string $reason
    ) {
        parent::__construct($message);
    }

    /**
     * Создать исключение для несоответствия типа токена.
     *
     * Фактическое значение — mixed, а не string: claim мог отсутствовать или
     * оказаться не строкой. Под strict_types такой аргумент в string-параметре
     * давал TypeError, то есть 500 вместо 401 на подписанном, но неполном токене.
     */
    public static function invalidTokenType(string $expected, mixed $actual): self
    {
        return new self(
            sprintf("Expected token type '%s', got '%s'", $expected, self::describe($actual)),
            'invalid_token_type'
        );
    }

    /**
     * Создать исключение для невалидного issuer.
     */
    public static function invalidIssuer(mixed $issuer): self
    {
        return new self(
            'Invalid issuer: '.self::describe($issuer),
            'invalid_issuer'
        );
    }

    /**
     * Создать исключение для невалидной audience.
     */
    public static function invalidAudience(mixed $audience): self
    {
        return new self(
            'Invalid audience: '.self::describe($audience),
            'invalid_audience'
        );
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::UNAUTHORIZED;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return ['reason' => $this->reason];
    }

    protected function errorDetail(): string
    {
        return 'Invalid or malformed authentication token';
    }

    /**
     * Диагностическое представление значения claim'а для сообщения исключения.
     * Наружу оно не идёт — клиент получает errorDetail() и reason, — поэтому
     * здесь важна не безопасность, а то, чтобы приведение типа не падало само.
     */
    private static function describe(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return implode(',', array_map(static fn (mixed $item): string => self::describe($item), $value));
        }

        return is_scalar($value) ? (string) $value : get_debug_type($value);
    }
}
