<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Events;

use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\Models\MediaVariant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: медиа-файл обработан (сгенерирован вариант).
 *
 * Отправляется после успешной генерации варианта медиа-файла.
 * Используется для логирования, уведомлений и автоматических интеграций (CDN purge).
 *
 * @package Polymorph\Platform\Domain\Media\Events
 */
final class MediaProcessed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param \Polymorph\Platform\Domain\Media\Core\Models\Media $media Медиа-файл
     * @param \Polymorph\Platform\Domain\Media\Core\Models\MediaVariant $variant Сгенерированный вариант
     */
    public function __construct(
        public readonly Media $media,
        public readonly MediaVariant $variant,
    ) {
    }
}

