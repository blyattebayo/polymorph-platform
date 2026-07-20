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
 * Исключение: ошибка генерации варианта медиа
 */
class VariantGenerationException extends RuntimeException implements ErrorConvertible
{
    private ?string $variantName = null;
    private ?string $mediaId = null;

    /**
     * Создать исключение для конкретного варианта
     */
    public static function forVariant(
        string $variantName,
        string $mediaId,
        string $message,
        ?Throwable $previous = null
    ): self {
        $exception = new self(
            "Variant '{$variantName}' generation failed for media '{$mediaId}': {$message}",
            0,
            $previous
        );
        $exception->variantName = $variantName;
        $exception->mediaId = $mediaId;
        return $exception;
    }

    /**
     * Создать исключение для ошибки изменения размера
     */
    public static function resizeFailed(string $variantName, string $mediaId, ?Throwable $previous = null): self
    {
        return self::forVariant($variantName, $mediaId, 'Image resize failed', $previous);
    }

    /**
     * Создать исключение для ошибки конвертации формата
     */
    public static function conversionFailed(
        string $variantName,
        string $mediaId,
        string $targetFormat,
        ?Throwable $previous = null
    ): self {
        return self::forVariant(
            $variantName,
            $mediaId,
            "Format conversion to '{$targetFormat}' failed",
            $previous
        );
    }

    /**
     * Создать исключение для ошибки сохранения
     */
    public static function saveFailed(string $variantName, string $mediaId, ?Throwable $previous = null): self
    {
        return self::forVariant($variantName, $mediaId, 'Failed to save variant', $previous);
    }

    /**
     * Получить имя варианта
     */
    public function getVariantName(): ?string
    {
        return $this->variantName;
    }

    /**
     * Получить ID медиа-файла
     */
    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    /**
     * Конвертировать в ErrorPayload для API
     */
    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::MEDIA_VARIANT_ERROR)
            ->detail('Failed to generate media variant.')
            ->meta([
                'resource' => 'media_variant',
                'variant' => $this->variantName,
                'media_id' => $this->mediaId,
            ])
            ->build();
    }
}
