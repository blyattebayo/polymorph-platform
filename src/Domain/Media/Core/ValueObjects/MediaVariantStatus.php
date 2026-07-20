<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\ValueObjects;

/**
 * Статус генерации варианта медиа.
 */
enum MediaVariantStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    /**
     * Проверить, завершена ли обработка (успешно или с ошибкой).
     */
    public function isFinished(): bool
    {
        return $this === self::Ready || $this === self::Failed;
    }

    /**
     * Проверить, идет ли обработка.
     */
    public function isProcessing(): bool
    {
        return $this === self::Processing || $this === self::Queued;
    }
}
