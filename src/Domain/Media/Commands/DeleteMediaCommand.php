<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Commands;

use Polymorph\Platform\Domain\Media\Core\Models\Media;

/**
 * Command для soft delete медиа.
 * 
 * Часть CQRS паттерна - операция изменения состояния.
 *
 * @package Polymorph\Platform\Domain\Media\Commands
 */
final readonly class DeleteMediaCommand
{
    /**
     * Soft delete медиа.
     *
     * @param Media $media Медиа для удаления
     * @return void
     */
    public function execute(Media $media): void
    {
        $media->delete();
    }
}
