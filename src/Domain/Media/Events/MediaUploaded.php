<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polymorph\Platform\Domain\Media\Core\Models\Media;

/**
 * Событие: медиа-файл загружен.
 *
 * Отправляется после успешной загрузки и сохранения медиа-файла в БД.
 * Используется для логирования, уведомлений и автоматических интеграций (CDN purge).
 */
final class MediaUploaded
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Media  $media  Загруженный медиа-файл
     */
    public function __construct(
        public readonly Media $media,
    ) {}
}
