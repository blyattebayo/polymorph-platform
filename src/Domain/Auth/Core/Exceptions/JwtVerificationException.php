<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение: ошибка верификации JWT токена.
 */
class JwtVerificationException extends RuntimeException implements ErrorConvertible
{
    public function __construct(
        string $message,
        public readonly string $reason
    ) {
        parent::__construct($message);
    }

    /**
     * Создать исключение для несоответствия типа токена.
     */
    public static function invalidTokenType(string $expected, string $actual): self
    {
        return new self(
            "Expected token type '{$expected}', got '{$actual}'",
            'invalid_token_type'
        );
    }

    /**
     * Создать исключение для невалидного issuer.
     */
    public static function invalidIssuer(string $issuer): self
    {
        return new self(
            "Invalid issuer: {$issuer}",
            'invalid_issuer'
        );
    }

    /**
     * Создать исключение для невалидной audience.
     *
     * @param  string|array<int, string>|null  $audience
     */
    public static function invalidAudience(string|array|null $audience): self
    {
        $actual = is_array($audience)
            ? implode(',', array_map(static fn (mixed $value): string => (string) $value, $audience))
            : (string) ($audience ?? '');

        return new self(
            "Invalid audience: {$actual}",
            'invalid_audience'
        );
    }

    /**
     * Конвертировать в ErrorPayload для API.
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::UNAUTHORIZED)
            ->detail('Invalid or malformed authentication token')
            ->meta(['reason' => $this->reason])
            ->build();
    }
}
