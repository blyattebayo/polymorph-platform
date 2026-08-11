<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Console;

use Illuminate\Console\Command;
use Polymorph\Platform\Domain\Auth\Application\Contracts\Clock;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthStore;

final class PruneOAuthCredentialsCommand extends Command
{
    protected $signature = 'auth:prune-oauth';

    protected $description = 'Delete expired OAuth authorization codes, access tokens, and grants';

    public function handle(OAuthStore $store, Clock $clock): int
    {
        $count = $store->prune($clock->now());
        $this->components->info("Pruned {$count} expired OAuth credential rows.");

        return self::SUCCESS;
    }
}
