<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;
use Throwable;

/**
 * Исключение: ошибка работы с хранилищем медиа
 */
class MediaStorageException extends RuntimeException implements ErrorConvertible
{
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
     * Конвертировать в ErrorPayload для API
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::MEDIA_DOWNLOAD_ERROR)
            ->detail('Failed to access media storage.')
            ->meta(['operation' => $this->operation])
            ->build();
    }
}
