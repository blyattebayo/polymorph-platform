<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polymorph\Platform\Domain\Media\Core\Models\Media;

/**
 * Событие force delete Media (безвозвратное удаление)
 */
class MediaForceDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Media $media
    ) {}
}
