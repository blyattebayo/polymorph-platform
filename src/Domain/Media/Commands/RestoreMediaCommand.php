<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Commands;

use Polymorph\Platform\Domain\Media\Core\Models\Media;

/**
 * Command для восстановления soft-deleted медиа.
 * 
 * Часть CQRS паттерна - операция изменения состояния.
 *
 * @package Polymorph\Platform\Domain\Media\Commands
 */
final readonly class RestoreMediaCommand
{
    /**
     * Восстановить soft-deleted медиа.
     *
     * @param Media $media Медиа для восстановления
     * @return void
     */
    public function execute(Media $media): void
    {
        $media->restore();
    }
}
