<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

/**
 * Исключение: медиа-файл не найден
 */
class MediaNotFoundException extends RuntimeException implements ErrorConvertible
{
    private ?string $mediaId = null;

    private ?string $checksum = null;

    /**
     * Создать исключение для ID
     */
    public static function byId(string $id): self
    {
        $exception = new self("Media with ID '{$id}' not found");
        $exception->mediaId = $id;

        return $exception;
    }

    /**
     * Создать исключение для checksum
     */
    public static function byChecksum(string $checksum): self
    {
        $exception = new self("Media with checksum '{$checksum}' not found");
        $exception->checksum = $checksum;

        return $exception;
    }

    /**
     * Создать исключение для path
     */
    public static function byPath(string $disk, string $path): self
    {
        return new self("Media on disk '{$disk}' with path '{$path}' not found");
    }

    /**
     * Конвертировать в ErrorPayload для API
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::NOT_FOUND)
            ->detail($this->getMessage())
            ->meta([
                'resource' => 'media',
                'media_id' => $this->mediaId,
                'checksum' => $this->checksum,
            ])
            ->build();
    }
}
