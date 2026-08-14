<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\ValueObjects;

enum MediaProcessingState: string
{
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return match ($this) {
            self::Uploading => in_array($next, [self::Processing, self::Failed], true),
            self::Processing => in_array($next, [self::Ready, self::Failed], true),
            self::Ready, self::Failed => $next === self::Processing,
        };
    }
}
