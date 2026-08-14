<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Services;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaProcessingState;

/** Serializes and idempotently validates asset-level processing transitions. */
final class MediaProcessingStateService
{
    public function transition(string $mediaId, MediaProcessingState $next): MediaProcessingState
    {
        return DB::transaction(function () use ($mediaId, $next): MediaProcessingState {
            if (! DB::table('media')->where('id', $mediaId)->lockForUpdate()->exists()) {
                throw new \InvalidArgumentException("Media '{$mediaId}' does not exist.");
            }
            $value = DB::table('dp_media_processing_states')->where('media_id', $mediaId)
                ->lockForUpdate()->value('state');
            $current = is_string($value) ? MediaProcessingState::from($value) : MediaProcessingState::Ready;
            if (! $current->canTransitionTo($next)) {
                throw new \LogicException("Invalid media processing transition {$current->value} -> {$next->value}.");
            }
            if ($current !== $next) {
                DB::table('dp_media_processing_states')->updateOrInsert([
                    'media_id' => $mediaId,
                ], [
                    'state' => $next->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $next;
        });
    }
}
