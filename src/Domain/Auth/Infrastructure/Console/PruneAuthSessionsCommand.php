<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class PruneAuthSessionsCommand extends Command
{
    protected $signature = 'auth:prune-sessions';

    protected $description = 'Delete expired authentication sessions';

    public function handle(): int
    {
        $deleted = DB::table('auth_sessions')
            ->where('expires_at', '<=', Date::now('UTC')->format('Y-m-d H:i:s.uP'))
            ->delete();

        $this->info(sprintf('Deleted %d expired authentication session rows.', $deleted));

        return self::SUCCESS;
    }
}
