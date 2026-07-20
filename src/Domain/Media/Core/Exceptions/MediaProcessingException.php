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
 * Исключение: ошибка обработки медиа-файла
 */
class MediaProcessingException extends RuntimeException implements ErrorConvertible
{
    private ?string $mediaId = null;
    private ?string $processingStage = null;

    /**
     * Создать исключение для конкретного медиа
     */
    public static function forMedia(string $mediaId, string $message, ?Throwable $previous = null): self
    {
        $exception = new self("Media processing failed for ID '{$mediaId}': {$message}", 0, $previous);
        $exception->mediaId = $mediaId;
        return $exception;
    }

    /**
     * Создать исключение с указанием стадии обработки
     */
    public static function atStage(string $stage, string $message, ?Throwable $previous = null): self
    {
        $exception = new self("Media processing failed at stage '{$stage}': {$message}", 0, $previous);
        $exception->processingStage = $stage;
        return $exception;
    }

    /**
     * Создать исключение для ошибки извлечения метаданных
     */
    public static function metadataExtraction(string $message, ?Throwable $previous = null): self
    {
        return self::atStage('metadata_extraction', $message, $previous);
    }

    /**
     * Создать исключение для ошибки валидации
     */
    public static function validation(string $message, ?Throwable $previous = null): self
    {
        return self::atStage('validation', $message, $previous);
    }

    /**
     * Получить ID медиа-файла
     */
    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    /**
     * Получить стадию обработки
     */
    public function getProcessingStage(): ?string
    {
        return $this->processingStage;
    }

    /**
     * Конвертировать в ErrorPayload для API
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::INTERNAL_SERVER_ERROR)
            ->detail($this->getMessage())
            ->meta([
                'resource' => 'media',
                'media_id' => $this->mediaId,
                'processing_stage' => $this->processingStage,
            ])
            ->build();
    }
}
