<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Events;

use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: медиа-файл удалён.
 *
 * Отправляется после мягкого удаления (soft delete) медиа-файла.
 * Используется для логирования, уведомлений и автоматических интеграций (CDN purge).
 *
 * @property-read \Polymorph\Platform\Domain\Media\Core\Models\Media $media
 * @package Polymorph\Platform\Domain\Media\Events
 */
final class MediaDeleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param \Polymorph\Platform\Domain\Media\Core\Models\Media $media Удалённый медиа-файл
     */
    public function __construct(
        public readonly Media $media,
    ) {
    }
}

