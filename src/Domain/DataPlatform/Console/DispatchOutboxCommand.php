<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\DataPlatform\Outbox\OutboxDispatcher;

final class DispatchOutboxCommand extends Command
{
    protected $signature = 'data-platform:outbox-dispatch {--limit= : Maximum events in this batch}';

    protected $description = 'Dispatch one batch from the transactional data-platform outbox.';

    public function handle(OutboxDispatcher $dispatcher): int
    {
        $limit = $this->option('limit');
        $count = $dispatcher->dispatchBatch(limit: $limit === null ? null : max(1, (int) $limit));
        $this->info("Delivered {$count} outbox event(s).");

        return self::SUCCESS;
    }
}
