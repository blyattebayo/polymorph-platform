<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Exceptions;

use InvalidArgumentException;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

/**
 * Исключение: недопустимый тип медиа
 */
class InvalidMediaTypeException extends InvalidArgumentException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private ?string $mimeType = null;

    private ?string $expectedKind = null;

    /**
     * Создать исключение для неподдерживаемого MIME типа
     */
    public static function unsupportedMimeType(string $mimeType, ?array $allowedMimeTypes = null): self
    {
        $message = "Unsupported MIME type: '{$mimeType}'";

        if ($allowedMimeTypes) {
            $message .= '. Allowed types: '.implode(', ', $allowedMimeTypes);
        }

        $exception = new self($message);
        $exception->mimeType = $mimeType;

        return $exception;
    }

    /**
     * Создать исключение для несоответствия типа медиа
     */
    public static function mismatchedKind(MediaKind $expected, MediaKind $actual): self
    {
        $exception = new self(
            "Media kind mismatch: expected '{$expected->value}', got '{$actual->value}'"
        );
        $exception->expectedKind = $expected->value;

        return $exception;
    }

    /**
     * Создать исключение для операции, недопустимой для типа медиа
     */
    public static function invalidOperationForKind(string $operation, MediaKind $kind): self
    {
        return new self("Operation '{$operation}' is not supported for media kind '{$kind->value}'");
    }

    /**
     * Создать исключение для некорректного расширения файла
     */
    public static function invalidExtension(string $extension, ?string $mimeType = null): self
    {
        $message = "Invalid file extension: '{$extension}'";

        if ($mimeType) {
            $message .= " for MIME type '{$mimeType}'";
        }

        return new self($message);
    }

    /**
     * Получить MIME тип
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * Получить ожидаемый тип медиа
     */
    public function getExpectedKind(): ?string
    {
        return $this->expectedKind;
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::VALIDATION_ERROR;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [
            'mime_type' => $this->mimeType,
            'expected_kind' => $this->expectedKind,
        ];
    }
}
