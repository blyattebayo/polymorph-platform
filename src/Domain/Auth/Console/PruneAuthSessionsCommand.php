<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\Auth\Core\Contracts\RefreshSessionRepository;

final class PruneAuthSessionsCommand extends Command
{
    protected $signature = 'auth:prune-sessions';

    protected $description = 'Delete expired auth session rows after their retention window';

    public function handle(RefreshSessionRepository $sessions): int
    {
        $deleted = $sessions->pruneExpired();

        $this->info(sprintf('Deleted %d expired auth session rows.', $deleted));

        return self::SUCCESS;
    }
}
