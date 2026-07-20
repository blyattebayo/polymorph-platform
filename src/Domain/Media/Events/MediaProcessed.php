<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\Models\MediaVariant;

/**
 * Событие: медиа-файл обработан (сгенерирован вариант).
 *
 * Отправляется после успешной генерации варианта медиа-файла.
 * Используется для логирования, уведомлений и автоматических интеграций (CDN purge).
 */
final class MediaProcessed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Media  $media  Медиа-файл
     * @param  MediaVariant  $variant  Сгенерированный вариант
     */
    public function __construct(
        public readonly Media $media,
        public readonly MediaVariant $variant,
    ) {}
}
