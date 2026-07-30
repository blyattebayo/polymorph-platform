<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Исключение: медиа-файл не найден.
 *
 * meta ресурсных 404 едина для всех доменов: {resource, identifier,
 * identifier_type}, без null-ключей под неиспользованные способы поиска.
 */
class MediaNotFoundException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private function __construct(
        string $message,
        private readonly string $identifier,
        private readonly string $identifierType,
    ) {
        parent::__construct($message);
    }

    /**
     * Создать исключение для ID
     */
    public static function byId(string $id): self
    {
        return new self("Media with ID '{$id}' not found", $id, 'id');
    }

    /**
     * Создать исключение для checksum
     */
    public static function byChecksum(string $checksum): self
    {
        return new self("Media with checksum '{$checksum}' not found", $checksum, 'checksum');
    }

    /**
     * Создать исключение для path
     */
    public static function byPath(string $disk, string $path): self
    {
        // Диск — деталь инфраструктуры: в сообщение (лог) идёт, в meta ответа — нет.
        return new self("Media on disk '{$disk}' with path '{$path}' not found", $path, 'path');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::NOT_FOUND;
    }

    protected function errorTitle(): ?string
    {
        return 'Media not found';
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [
            'resource' => 'media',
            'identifier' => $this->identifier,
            'identifier_type' => $this->identifierType,
        ];
    }
}
