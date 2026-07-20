<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Events;

use Polymorph\Platform\Domain\Media\Core\Models\MediaVariant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие успешной генерации варианта
 */
class VariantGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly MediaVariant $variant
    ) {}
}
