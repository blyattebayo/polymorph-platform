<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polymorph\Platform\Domain\Media\Core\Models\Media;

/**
 * Событие: медиа-файл удалён.
 *
 * Отправляется после мягкого удаления (soft delete) медиа-файла.
 * Используется для логирования, уведомлений и автоматических интеграций (CDN purge).
 *
 * @property-read Media $media
 */
final class MediaDeleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Media  $media  Удалённый медиа-файл
     */
    public function __construct(
        public readonly Media $media,
    ) {}
}
