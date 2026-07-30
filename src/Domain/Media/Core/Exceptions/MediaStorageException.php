<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;
use Throwable;

/**
 * Исключение: ошибка работы с хранилищем медиа
 */
class MediaStorageException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private ?string $disk = null;

    private ?string $path = null;

    private ?string $operation = null;

    /**
     * Создать исключение для ошибки записи
     */
    public static function writeFailed(string $disk, string $path, ?Throwable $previous = null): self
    {
        $exception = new self(
            "Failed to write file to storage. Disk: '{$disk}', Path: '{$path}'",
            0,
            $previous
        );
        $exception->disk = $disk;
        $exception->path = $path;
        $exception->operation = 'write';

        return $exception;
    }

    /**
     * Создать исключение для ошибки чтения
     */
    public static function readFailed(string $disk, string $path, ?Throwable $previous = null): self
    {
        $exception = new self(
            "Failed to read file from storage. Disk: '{$disk}', Path: '{$path}'",
            0,
            $previous
        );
        $exception->disk = $disk;
        $exception->path = $path;
        $exception->operation = 'read';

        return $exception;
    }

    /**
     * Создать исключение для ошибки удаления
     */
    public static function deleteFailed(string $disk, string $path, ?Throwable $previous = null): self
    {
        $exception = new self(
            "Failed to delete file from storage. Disk: '{$disk}', Path: '{$path}'",
            0,
            $previous
        );
        $exception->disk = $disk;
        $exception->path = $path;
        $exception->operation = 'delete';

        return $exception;
    }

    /**
     * Создать исключение для ошибки перемещения
     */
    public static function moveFailed(
        string $fromDisk,
        string $fromPath,
        string $toDisk,
        string $toPath,
        ?Throwable $previous = null
    ): self {
        $exception = new self(
            "Failed to move file. From: '{$fromDisk}:{$fromPath}' To: '{$toDisk}:{$toPath}'",
            0,
            $previous
        );
        $exception->disk = $fromDisk;
        $exception->path = $fromPath;
        $exception->operation = 'move';

        return $exception;
    }

    /**
     * Создать исключение для отсутствующего диска
     */
    public static function diskNotFound(string $disk): self
    {
        $exception = new self("Storage disk '{$disk}' not found or not configured");
        $exception->disk = $disk;

        return $exception;
    }

    /**
     * Создать исключение для превышения квоты хранилища
     */
    public static function quotaExceeded(string $disk, int $availableBytes, int $requiredBytes): self
    {
        $exception = new self(
            "Storage quota exceeded on disk '{$disk}'. Available: {$availableBytes} bytes, Required: {$requiredBytes} bytes"
        );
        $exception->disk = $disk;

        return $exception;
    }

    /**
     * Создать исключение для ошибки генерации URL
     */
    public static function urlGenerationFailed(string $disk, string $path, ?Throwable $previous = null): self
    {
        $exception = new self(
            "Failed to generate URL for file. Disk: '{$disk}', Path: '{$path}'",
            0,
            $previous
        );
        $exception->disk = $disk;
        $exception->path = $path;
        $exception->operation = 'url_generation';

        return $exception;
    }

    /**
     * Получить имя диска
     */
    public function getDisk(): ?string
    {
        return $this->disk;
    }

    /**
     * Получить путь к файлу
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Получить операцию
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Код выводится из операции: MEDIA_DOWNLOAD_ERROR — только про выдачу файла
     * клиенту. Раньше он стоял на всех операциях, и сбой записи при загрузке или
     * переполнение квоты приезжали клиенту и в лог как «Failed to download media».
     */
    public function errorCode(): ErrorCode
    {
        return $this->operation === 'url_generation'
            ? ErrorCode::MEDIA_DOWNLOAD_ERROR
            : ErrorCode::MEDIA_STORAGE_ERROR;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return ['operation' => $this->operation];
    }

    protected function errorDetail(): string
    {
        // Сообщение исключения содержит диск и путь — это диагностика для лога,
        // наружу идёт нейтральный текст.
        return $this->operation === 'url_generation'
            ? 'Failed to generate download URL.'
            : 'Failed to access media storage.';
    }
}
