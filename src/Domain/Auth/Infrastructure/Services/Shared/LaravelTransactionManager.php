<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared;

use Closure;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Auth\Application\Contracts\TransactionManager;

final class LaravelTransactionManager implements TransactionManager
{
    public function run(Closure $operation): mixed
    {
        return DB::transaction($operation, 3);
    }
}
