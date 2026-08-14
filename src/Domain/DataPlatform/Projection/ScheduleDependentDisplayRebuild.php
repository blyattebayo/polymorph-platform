<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Polymorph\Platform\Domain\DataPlatform\Outbox\DataPlatformEvent;
use Polymorph\Platform\Domain\DataPlatform\Outbox\RecordEventType;

/** Converts committed record changes into durable reverse-display maintenance. */
final class ScheduleDependentDisplayRebuild
{
    public function __construct(private readonly ProjectionRebuildScheduler $scheduler) {}

    public function handle(DataPlatformEvent $event): void
    {
        if (! in_array($event->type, RecordEventType::values(), true)) {
            return;
        }

        $recordId = (int) ($event->payload['record_id'] ?? 0);
        if ($recordId > 0) {
            $this->scheduler->scheduleDependents($recordId);
        }
    }
}
