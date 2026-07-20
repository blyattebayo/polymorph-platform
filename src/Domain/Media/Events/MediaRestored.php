<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polymorph\Platform\Domain\Media\Core\Models\Media;

/**
 * Событие восстановления Media после soft delete
 */
class MediaRestored
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Media $media
    ) {}
}
