<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Commands;

use Polymorph\Platform\Domain\Media\Core\Models\Media;

/**
 * Command для восстановления soft-deleted медиа.
 *
 * Часть CQRS паттерна - операция изменения состояния.
 */
final readonly class RestoreMediaCommand
{
    /**
     * Восстановить soft-deleted медиа.
     *
     * @param  Media  $media  Медиа для восстановления
     */
    public function execute(Media $media): void
    {
        $media->restore();
    }
}
